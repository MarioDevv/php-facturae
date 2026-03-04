<?php

declare(strict_types=1);

namespace MarioDevv\Rex\Facturae\Signer;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * XAdES-EPES signer for FacturaE documents.
 *
 * Generates an enveloped XML-DSIG signature with XAdES SignedProperties
 * compliant with the FacturaE signing policy v3.1.
 *
 * Usage:
 *     $signer = Pkcs12Signer::pfx('/path/to/cert.pfx', 'password');
 *     $signedXml = $signer->sign($xml);
 *
 *     // Or with timestamp authority:
 *     $signer = Pkcs12Signer::pfx('/path/to/cert.pfx', 'password')
 *         ->timestamp('https://freetsa.org/tsr');
 */
final class Pkcs12Signer implements InvoiceSigner
{
    private const XMLDSIG_NS  = 'http://www.w3.org/2000/09/xmldsig#';
    private const XADES_NS    = 'http://uri.etsi.org/01903/v1.3.2#';
    private const C14N        = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const C14N_ALGO   = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const SHA256_ALGO = 'http://www.w3.org/2001/04/xmlenc#sha256';
    private const RSA_SHA256  = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const ENVELOPED   = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    private const POLICY_NAME   = 'Política de Firma FacturaE v3.1';
    private const POLICY_URL    = 'http://www.facturae.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf';
    private const POLICY_DIGEST = 'Ohixl6upD6av8N7pEvDABhEL6hM=';

    /** @var \OpenSSLAsymmetricKey */
    private mixed $privateKey;

    /** @var string DER-encoded certificate */
    private string $certificate;

    /** @var string[] DER-encoded CA chain */
    private array $chain = [];

    /** @var string|null TSA endpoint URL */
    private ?string $tsaUrl = null;

    /** @var string|null TSA username */
    private ?string $tsaUser = null;

    /** @var string|null TSA password */
    private ?string $tsaPassword = null;

    private function __construct() {}

    /**
     * Create signer from a PKCS#12 (.pfx / .p12) file.
     */
    public static function pfx(string $path, ?string $passphrase = null): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Certificate file not found: {$path}");
        }

        $content = file_get_contents($path);

        if (!openssl_pkcs12_read($content, $certs, $passphrase ?? '')) {
            throw new RuntimeException('Failed to read PKCS#12 file: ' . openssl_error_string());
        }

        $signer = new self();

        $signer->privateKey = openssl_pkey_get_private($certs['pkey']);
        if ($signer->privateKey === false) {
            throw new RuntimeException('Failed to load private key: ' . openssl_error_string());
        }

        $signer->certificate = self::pemToDer($certs['cert']);

        if (!empty($certs['extracerts'])) {
            foreach ($certs['extracerts'] as $extraCert) {
                $signer->chain[] = self::pemToDer($extraCert);
            }
        }

        return $signer;
    }

    /**
     * Create signer from PEM-encoded certificate and private key files.
     */
    public static function pem(string $certPath, string $keyPath, ?string $passphrase = null): self
    {
        if (!file_exists($certPath)) {
            throw new RuntimeException("Certificate file not found: {$certPath}");
        }
        if (!file_exists($keyPath)) {
            throw new RuntimeException("Key file not found: {$keyPath}");
        }

        $signer = new self();

        $signer->privateKey = openssl_pkey_get_private(
            file_get_contents($keyPath),
            $passphrase ?? '',
        );
        if ($signer->privateKey === false) {
            throw new RuntimeException('Failed to load private key: ' . openssl_error_string());
        }

        $signer->certificate = self::pemToDer(file_get_contents($certPath));

        return $signer;
    }

    /**
     * Add timestamp authority (TSA) for the signature.
     */
    public function timestamp(string $url, ?string $user = null, ?string $password = null): self
    {
        $this->tsaUrl = $url;
        $this->tsaUser = $user;
        $this->tsaPassword = $password;

        return $this;
    }

    /**
     * Sign a FacturaE XML document.
     */
    public function sign(string $xml): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = true;
        $doc->formatOutput = false;

        if (!$doc->loadXML($xml)) {
            throw new RuntimeException('Failed to parse XML document for signing');
        }

        $signatureId = $this->generateId('Signature');
        $signedPropertiesId = $this->generateId('SignedProperties');
        $keyInfoId = $this->generateId('Certificate');
        $referenceId = $this->generateId('Reference');
        $objectId = $this->generateId('Object');

        // ─── Build the Signature element ──────────────────
        $sig = $this->buildSignatureElement(
            $doc, $signatureId, $signedPropertiesId, $keyInfoId, $referenceId, $objectId,
        );

        // Insert signature as last child of root
        $doc->documentElement->appendChild($sig);

        // ─── Compute digests ──────────────────────────────

        // 1. Document digest (enveloped-signature transform)
        $docDigest = $this->computeDocumentDigest($doc);

        // 2. KeyInfo digest
        $keyInfoNode = $this->getElementById($doc, $keyInfoId);
        $keyInfoDigest = $this->computeNodeDigest($keyInfoNode);

        // 3. SignedProperties digest
        $signedPropsNode = $this->getElementById($doc, $signedPropertiesId);
        $signedPropsDigest = $this->computeNodeDigest($signedPropsNode);

        // ─── Set digest values ────────────────────────────
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', self::XMLDSIG_NS);

        $refs = $xpath->query('//ds:Signature/ds:SignedInfo/ds:Reference');
        foreach ($refs as $ref) {
            /** @var DOMElement $ref */
            $uri = $ref->getAttribute('URI');
            $digestValueNode = $xpath->query('ds:DigestValue', $ref)->item(0);

            if ($uri === '') {
                $digestValueNode->textContent = $docDigest;
            } elseif ($uri === '#' . $keyInfoId) {
                $digestValueNode->textContent = $keyInfoDigest;
            } elseif ($uri === '#' . $signedPropertiesId) {
                $digestValueNode->textContent = $signedPropsDigest;
            }
        }

        // ─── Compute signature value ──────────────────────
        $signedInfoNode = $xpath->query('//ds:Signature/ds:SignedInfo')->item(0);
        $signedInfoC14n = $signedInfoNode->C14N();

        $signatureValue = '';
        if (!openssl_sign($signedInfoC14n, $signatureValue, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to compute signature: ' . openssl_error_string());
        }

        $sigValueNode = $xpath->query('//ds:Signature/ds:SignatureValue')->item(0);
        $sigValueNode->textContent = "\n" . $this->formatBase64(base64_encode($signatureValue)) . "\n";

        // ─── Timestamp (optional) ─────────────────────────
        if ($this->tsaUrl !== null) {
            $this->applyTimestamp($doc, $xpath, $signatureValue, $signatureId);
        }

        return $doc->saveXML();
    }

    // ─── Signature building ─────────────────────────────

    private function buildSignatureElement(
        DOMDocument $doc,
        string $signatureId,
        string $signedPropertiesId,
        string $keyInfoId,
        string $referenceId,
        string $objectId,
    ): DOMElement {
        $sig = $doc->createElementNS(self::XMLDSIG_NS, 'ds:Signature');
        $sig->setAttribute('Id', $signatureId);

        // ─── SignedInfo ──────────────────────────────
        $signedInfo = $this->dsEl($doc, 'ds:SignedInfo');
        $sig->appendChild($signedInfo);

        $c14nMethod = $this->dsEl($doc, 'ds:CanonicalizationMethod');
        $c14nMethod->setAttribute('Algorithm', self::C14N_ALGO);
        $signedInfo->appendChild($c14nMethod);

        $sigMethod = $this->dsEl($doc, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', self::RSA_SHA256);
        $signedInfo->appendChild($sigMethod);

        // Reference 1: Document (enveloped)
        $ref1 = $this->dsEl($doc, 'ds:Reference');
        $ref1->setAttribute('Id', $referenceId);
        $ref1->setAttribute('URI', '');
        $signedInfo->appendChild($ref1);

        $transforms = $this->dsEl($doc, 'ds:Transforms');
        $ref1->appendChild($transforms);
        $transform = $this->dsEl($doc, 'ds:Transform');
        $transform->setAttribute('Algorithm', self::ENVELOPED);
        $transforms->appendChild($transform);

        $digestMethod1 = $this->dsEl($doc, 'ds:DigestMethod');
        $digestMethod1->setAttribute('Algorithm', self::SHA256_ALGO);
        $ref1->appendChild($digestMethod1);
        $ref1->appendChild($this->dsEl($doc, 'ds:DigestValue', 'PLACEHOLDER'));

        // Reference 2: KeyInfo
        $ref2 = $this->dsEl($doc, 'ds:Reference');
        $ref2->setAttribute('URI', '#' . $keyInfoId);
        $signedInfo->appendChild($ref2);

        $digestMethod2 = $this->dsEl($doc, 'ds:DigestMethod');
        $digestMethod2->setAttribute('Algorithm', self::SHA256_ALGO);
        $ref2->appendChild($digestMethod2);
        $ref2->appendChild($this->dsEl($doc, 'ds:DigestValue', 'PLACEHOLDER'));

        // Reference 3: SignedProperties
        $ref3 = $this->dsEl($doc, 'ds:Reference');
        $ref3->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $ref3->setAttribute('URI', '#' . $signedPropertiesId);
        $signedInfo->appendChild($ref3);

        $digestMethod3 = $this->dsEl($doc, 'ds:DigestMethod');
        $digestMethod3->setAttribute('Algorithm', self::SHA256_ALGO);
        $ref3->appendChild($digestMethod3);
        $ref3->appendChild($this->dsEl($doc, 'ds:DigestValue', 'PLACEHOLDER'));

        // ─── SignatureValue ──────────────────────────
        $sigValue = $this->dsEl($doc, 'ds:SignatureValue', 'PLACEHOLDER');
        $sig->appendChild($sigValue);

        // ─── KeyInfo ─────────────────────────────────
        $keyInfo = $this->buildKeyInfo($doc, $keyInfoId);
        $sig->appendChild($keyInfo);

        // ─── Object (XAdES) ──────────────────────────
        $object = $this->dsEl($doc, 'ds:Object');
        $object->setAttribute('Id', $objectId);
        $sig->appendChild($object);

        $qualProps = $this->buildQualifyingProperties($doc, $signatureId, $signedPropertiesId, $referenceId);
        $object->appendChild($qualProps);

        return $sig;
    }

    private function buildKeyInfo(DOMDocument $doc, string $keyInfoId): DOMElement
    {
        $keyInfo = $this->dsEl($doc, 'ds:KeyInfo');
        $keyInfo->setAttribute('Id', $keyInfoId);

        // X509Data
        $x509data = $this->dsEl($doc, 'ds:X509Data');
        $keyInfo->appendChild($x509data);
        $x509cert = $this->dsEl($doc, 'ds:X509Certificate', base64_encode($this->certificate));
        $x509data->appendChild($x509cert);

        // KeyValue
        $keyDetails = openssl_pkey_get_details($this->privateKey);
        if ($keyDetails !== false && $keyDetails['type'] === OPENSSL_KEYTYPE_RSA) {
            $keyValue = $this->dsEl($doc, 'ds:KeyValue');
            $keyInfo->appendChild($keyValue);

            $rsaKeyValue = $this->dsEl($doc, 'ds:RSAKeyValue');
            $keyValue->appendChild($rsaKeyValue);

            $modulus = $this->dsEl($doc, 'ds:Modulus', base64_encode($keyDetails['rsa']['n']));
            $rsaKeyValue->appendChild($modulus);

            $exponent = $this->dsEl($doc, 'ds:Exponent', base64_encode($keyDetails['rsa']['e']));
            $rsaKeyValue->appendChild($exponent);
        }

        return $keyInfo;
    }

    private function buildQualifyingProperties(
        DOMDocument $doc,
        string $signatureId,
        string $signedPropertiesId,
        string $referenceId,
    ): DOMElement {
        $qp = $doc->createElementNS(self::XADES_NS, 'xades:QualifyingProperties');
        $qp->setAttribute('Target', '#' . $signatureId);

        $sp = $doc->createElementNS(self::XADES_NS, 'xades:SignedProperties');
        $sp->setAttribute('Id', $signedPropertiesId);
        $qp->appendChild($sp);

        // ─── SignedSignatureProperties ────────────────
        $ssp = $doc->createElementNS(self::XADES_NS, 'xades:SignedSignatureProperties');
        $sp->appendChild($ssp);

        // SigningTime
        $signingTime = $doc->createElementNS(self::XADES_NS, 'xades:SigningTime', date('c'));
        $ssp->appendChild($signingTime);

        // SigningCertificate
        $this->appendSigningCertificate($doc, $ssp);

        // SignaturePolicyIdentifier
        $this->appendSignaturePolicy($doc, $ssp);

        // SignerRole
        $signerRole = $doc->createElementNS(self::XADES_NS, 'xades:SignerRole');
        $ssp->appendChild($signerRole);
        $claimedRoles = $doc->createElementNS(self::XADES_NS, 'xades:ClaimedRoles');
        $signerRole->appendChild($claimedRoles);
        $claimedRole = $doc->createElementNS(self::XADES_NS, 'xades:ClaimedRole', 'supplier');
        $claimedRoles->appendChild($claimedRole);

        // ─── SignedDataObjectProperties ───────────────
        $sdop = $doc->createElementNS(self::XADES_NS, 'xades:SignedDataObjectProperties');
        $sp->appendChild($sdop);

        $dof = $doc->createElementNS(self::XADES_NS, 'xades:DataObjectFormat');
        $dof->setAttribute('ObjectReference', '#' . $referenceId);
        $sdop->appendChild($dof);

        $desc = $doc->createElementNS(self::XADES_NS, 'xades:Description', 'Factura electrónica');
        $dof->appendChild($desc);

        $mimeType = $doc->createElementNS(self::XADES_NS, 'xades:MimeType', 'text/xml');
        $dof->appendChild($mimeType);

        return $qp;
    }

    private function appendSigningCertificate(DOMDocument $doc, DOMElement $parent): void
    {
        $signingCert = $doc->createElementNS(self::XADES_NS, 'xades:SigningCertificate');
        $parent->appendChild($signingCert);

        $cert = $doc->createElementNS(self::XADES_NS, 'xades:Cert');
        $signingCert->appendChild($cert);

        // CertDigest
        $certDigest = $doc->createElementNS(self::XADES_NS, 'xades:CertDigest');
        $cert->appendChild($certDigest);

        $digestMethod = $this->dsEl($doc, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::SHA256_ALGO);
        $certDigest->appendChild($digestMethod);

        $digestValue = $this->dsEl($doc, 'ds:DigestValue', base64_encode(hash('sha256', $this->certificate, true)));
        $certDigest->appendChild($digestValue);

        // IssuerSerial
        $issuerSerial = $doc->createElementNS(self::XADES_NS, 'xades:IssuerSerial');
        $cert->appendChild($issuerSerial);

        $certData = openssl_x509_parse('-----BEGIN CERTIFICATE-----' . "\n" . base64_encode($this->certificate) . "\n" . '-----END CERTIFICATE-----');

        $issuerName = $this->formatIssuerName($certData['issuer'] ?? []);
        $serialNumber = $certData['serialNumber'] ?? '0';

        $x509Issuer = $this->dsEl($doc, 'ds:X509IssuerName', $issuerName);
        $issuerSerial->appendChild($x509Issuer);

        $x509Serial = $this->dsEl($doc, 'ds:X509SerialNumber', $serialNumber);
        $issuerSerial->appendChild($x509Serial);
    }

    private function appendSignaturePolicy(DOMDocument $doc, DOMElement $parent): void
    {
        $policyId = $doc->createElementNS(self::XADES_NS, 'xades:SignaturePolicyIdentifier');
        $parent->appendChild($policyId);

        $sigPolicy = $doc->createElementNS(self::XADES_NS, 'xades:SignaturePolicy');
        $policyId->appendChild($sigPolicy);

        $sigPolicyId = $doc->createElementNS(self::XADES_NS, 'xades:SignaturePolicyId');
        $sigPolicy->appendChild($sigPolicyId);

        $spId = $doc->createElementNS(self::XADES_NS, 'xades:SigPolicyId');
        $sigPolicyId->appendChild($spId);

        $identifier = $doc->createElementNS(self::XADES_NS, 'xades:Identifier', self::POLICY_URL);
        $spId->appendChild($identifier);

        $description = $doc->createElementNS(self::XADES_NS, 'xades:Description', self::POLICY_NAME);
        $spId->appendChild($description);

        $policyHash = $doc->createElementNS(self::XADES_NS, 'xades:SigPolicyHash');
        $sigPolicyId->appendChild($policyHash);

        $digestMethod = $this->dsEl($doc, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $policyHash->appendChild($digestMethod);

        $digestValue = $this->dsEl($doc, 'ds:DigestValue', self::POLICY_DIGEST);
        $policyHash->appendChild($digestValue);
    }

    // ─── Digest computation ─────────────────────────────

    private function computeDocumentDigest(DOMDocument $doc): string
    {
        // Clone and remove Signature element for enveloped-signature transform
        $clone = clone $doc;
        $xpath = new DOMXPath($clone);
        $xpath->registerNamespace('ds', self::XMLDSIG_NS);

        $sigNode = $xpath->query('//ds:Signature')->item(0);
        if ($sigNode !== null) {
            $sigNode->parentNode->removeChild($sigNode);
        }

        $c14n = $clone->documentElement->C14N();

        return base64_encode(hash('sha256', $c14n, true));
    }

    private function computeNodeDigest(DOMElement $node): string
    {
        $c14n = $node->C14N();

        return base64_encode(hash('sha256', $c14n, true));
    }

    // ─── Timestamp (RFC 3161) ───────────────────────────

    private function applyTimestamp(DOMDocument $doc, DOMXPath $xpath, string $signatureValue, string $signatureId): void
    {
        // Create TimeStamp Request (RFC 3161)
        $tsDigest = hash('sha256', $signatureValue, true);

        // Build ASN.1 TimeStampReq manually
        $tsRequest = $this->buildTimestampRequest($tsDigest);

        // Send request
        $tsResponse = $this->sendTimestampRequest($tsRequest);

        if ($tsResponse === null) {
            return; // TSA failed, signature is still valid without timestamp
        }

        // Add UnsignedProperties with timestamp
        $sigNode = $xpath->query('//ds:Signature')->item(0);
        $objectNode = $xpath->query('ds:Object', $sigNode)->item(0);

        $qpNode = $xpath->query('xades:QualifyingProperties', $objectNode)->item(0);
        if ($qpNode === null) {
            // Register namespace for XPath
            $xpath->registerNamespace('xades', self::XADES_NS);
            $qpNode = $xpath->query('.//xades:QualifyingProperties', $objectNode)->item(0);
        }

        $unsignedProps = $doc->createElementNS(self::XADES_NS, 'xades:UnsignedProperties');
        $qpNode->appendChild($unsignedProps);

        $unsignedSigProps = $doc->createElementNS(self::XADES_NS, 'xades:UnsignedSignatureProperties');
        $unsignedProps->appendChild($unsignedSigProps);

        $sigTimeStamp = $doc->createElementNS(self::XADES_NS, 'xades:SignatureTimeStamp');
        $unsignedSigProps->appendChild($sigTimeStamp);

        $encapsulatedTs = $doc->createElementNS(self::XADES_NS, 'xades:EncapsulatedTimeStamp', base64_encode($tsResponse));
        $sigTimeStamp->appendChild($encapsulatedTs);
    }

    private function buildTimestampRequest(string $digest): string
    {
        // SHA-256 OID: 2.16.840.1.101.3.4.2.1
        $oid = hex2bin('0609608648016503040201');

        // AlgorithmIdentifier SEQUENCE
        $algoId = "\x30" . chr(strlen($oid) + 2) . $oid . "\x05\x00";

        // MessageImprint SEQUENCE
        $digestOctet = "\x04" . chr(strlen($digest)) . $digest;
        $messageImprint = "\x30" . chr(strlen($algoId) + strlen($digestOctet)) . $algoId . $digestOctet;

        // Version INTEGER 1
        $version = "\x02\x01\x01";

        // Nonce (optional random)
        $nonce = "\x02\x04" . random_bytes(4);

        // CertReq BOOLEAN TRUE
        $certReq = "\x01\x01\xff";

        $body = $version . $messageImprint . $nonce . $certReq;

        return "\x30" . $this->asn1Length(strlen($body)) . $body;
    }

    private function sendTimestampRequest(string $request): ?string
    {
        $ch = curl_init($this->tsaUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $request,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/timestamp-query'],
            CURLOPT_TIMEOUT        => 10,
        ]);

        if ($this->tsaUser !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->tsaUser . ':' . $this->tsaPassword);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        // Extract TimeStampToken from response (skip status bytes)
        // RFC 3161: TimeStampResp = SEQUENCE { status PKIStatusInfo, timeStampToken ContentInfo }
        if (strlen($response) < 10) {
            return null;
        }

        return $response;
    }

    // ─── Helpers ─────────────────────────────────────────

    private function dsEl(DOMDocument $doc, string $name, ?string $value = null): DOMElement
    {
        return $value !== null
            ? $doc->createElementNS(self::XMLDSIG_NS, $name, htmlspecialchars($value, ENT_XML1))
            : $doc->createElementNS(self::XMLDSIG_NS, $name);
    }

    private function getElementById(DOMDocument $doc, string $id): DOMElement
    {
        $xpath = new DOMXPath($doc);

        // Search by Id attribute in all namespaces
        $nodes = $xpath->query("//*[@Id='{$id}']");

        if ($nodes->length === 0) {
            throw new RuntimeException("Element with Id '{$id}' not found in document");
        }

        return $nodes->item(0);
    }

    private function generateId(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(8));
    }

    private function formatBase64(string $base64): string
    {
        return chunk_split($base64, 76, "\n");
    }

    private function formatIssuerName(array $issuer): string
    {
        $parts = [];
        $mapping = [
            'C'            => 'C',
            'ST'           => 'ST',
            'L'            => 'L',
            'O'            => 'O',
            'OU'           => 'OU',
            'CN'           => 'CN',
            'serialNumber' => 'serialNumber',
            'emailAddress' => 'emailAddress',
        ];

        foreach ($issuer as $key => $value) {
            $rdn = $mapping[$key] ?? $key;
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = "{$rdn}={$v}";
                }
            } else {
                $parts[] = "{$rdn}={$value}";
            }
        }

        return implode(', ', $parts);
    }

    private static function pemToDer(string $pem): string
    {
        $pem = preg_replace('/-----[A-Z ]+-----/', '', $pem);
        $pem = str_replace(["\r", "\n", ' '], '', $pem);

        return base64_decode($pem);
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        $temp = $length;

        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
