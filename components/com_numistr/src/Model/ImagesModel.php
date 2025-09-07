<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class NumistrModelImages extends BaseDatabaseModel
{
    public function fetchOne(int $id): ?array
    {
        // TODO: Integrate with com_numistr secure media pipeline.
        if ($id !== 12345) return null;
        return [
            'id'=>12345, 'variant_uid'=>'ntr:var:DEMO1', 'role'=>'obv',
            'caption'=>'Demo obverse', 'ordering'=>1,
            'thumb_url'=>'https://example.org/i/demo_t.webp',
            'medium_url_signed'=>'https://example.org/i/demo_m.webp?sig=...&exp=1731000000',
            'full_url_signed'  =>'https://example.org/i/demo_f.webp?sig=...&exp=1731000000',
            'expires_at'=>'2025-10-07T12:00:00Z'
        ];
    }

    public function serializeOne(array $r): array
    {
        // fields/include not needed here for MVP; add later if required.
        return $r;
    }
}
