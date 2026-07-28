<?php
declare(strict_types=1);

const FOOTER_NOTE_ROW_ID = 5;
const FOOTER_LOGO_ROW_ID = 6;
const FOOTER_POST_ORDER_ROW_ID = 7;
const FOOTER_HOMEPAGE_HEADING_ROW_ID = 8;
const FOOTER_DISPLAY_ROW_ID = 9;

/** @return int[] */
function footer_reserved_row_ids(): array
{
    return [
        FOOTER_NOTE_ROW_ID,
        FOOTER_LOGO_ROW_ID,
        FOOTER_POST_ORDER_ROW_ID,
        FOOTER_HOMEPAGE_HEADING_ROW_ID,
        FOOTER_DISPLAY_ROW_ID,
    ];
}
