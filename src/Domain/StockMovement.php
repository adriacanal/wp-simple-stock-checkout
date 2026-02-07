<?php

namespace WPSSC\Domain;

if (!defined('ABSPATH')) { exit; }

final class StockMovement
{
    public const TYPE_MANUAL_SALE = 'manual_sale';
    public const TYPE_ADJUSTMENT  = 'adjustment';

    public int $variant_id;
    public string $movement_type;
    public int $qty;
    public ?string $note;
    public ?int $created_by;

    public function __construct(
        int $variant_id,
        string $movement_type,
        int $qty,
        ?string $note = null,
        ?int $created_by = null
    ) {
        $this->variant_id = $variant_id;
        $this->movement_type = $movement_type;
        $this->qty = $qty;
        $this->note = $note;
        $this->created_by = $created_by;
    }
}
