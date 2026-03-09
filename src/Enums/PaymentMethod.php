<?php

declare(strict_types=1);

namespace PhpFacturae\Enums;

/**
 * Metodos de pago del XSD FacturaE 3.2.2.
 *
 * @see Facturae XSD PaymentMeansType
 */
enum PaymentMethod: string
{
    case Cash                    = '01';
    case DirectDebit             = '02';
    case Receipt                 = '03';
    case Transfer                = '04';
    case AcceptedBillOfExchange  = '05';
    case DocumentaryCredit       = '06';
    case ContractAward           = '07';
    case BillOfExchange          = '08';
    case TransferablePromissory  = '09';
    case PromissoryNote          = '10';
    case Cheque                  = '11';
    case Reimbursement           = '12';
    case Special                 = '13';
    case Setoff                  = '14';
    case Postgiro                = '15';
    case CertifiedCheque         = '16';
    case BankersDraft            = '17';
    case CashOnDelivery          = '18';
    case Card                    = '19';
}
