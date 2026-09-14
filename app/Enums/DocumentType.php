<?php

namespace App\Enums;

enum DocumentType: string
{
    case BillOfLading      = 'bill_of_lading';
    case Invoice           = 'invoice';
    case ExportTitle       = 'export_title';
    case DockReceipt       = 'dock_receipt';
    case VehicleRelease    = 'vehicle_release_form';
    case ShippingReceipt   = 'shipping_receipt';
    case AuctionPurchase   = 'auction_purchase_receipt';
    case Other             = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::BillOfLading->value    => 'Bill of Lading (BOL)',
            self::Invoice->value         => 'Invoice',
            self::ExportTitle->value     => 'Export Title',
            self::DockReceipt->value     => 'Dock Receipt',
            self::VehicleRelease->value  => 'Vehicle Release Form',
            self::ShippingReceipt->value => 'Shipping Receipt',
            self::AuctionPurchase->value => 'Auction Purchase Receipt',
            self::Other->value           => 'Other',
        ];
    }
}
