<?php
namespace App\Enums;

use Illuminate\Validation\Rules\Enum;

class SortTypes extends Enum
{
    const ID_ASC = 'id_asc';
    const ID_DESC = 'id_desc';
    const NAME_ASC = 'name_asc';
    const NAME_DESC = 'name_desc';
    const COMPATIBLE_ASC = 'compatible_asc';
    const COMPATIBLE_DESC = 'compatible_desc';
    const PRICE_ASC = 'price_asc';
    const PRICE_DESC = 'price_desc';
    const DISCOUNT_PRICE_ASC = 'discount_price_asc';
    const DISCOUNT_PRICE_DESC = 'discount_price_desc';

}
