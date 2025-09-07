<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class NumistrModelRegions extends BaseDatabaseModel
{
    // TODO: Move to DB/config; hard-coded for skeleton.
    private array $regions = [
        ['code'=>'ionia','name_tr'=>'İyonya','name_en'=>'Ionia'],
        ['code'=>'mysia','name_tr'=>'Mysia','name_en'=>'Mysia']
        // ... (12 bölge tamamlanacak)
    ];

    public function fetchList(): array
    {
        return $this->regions;
    }

    public function fetchOne(string $code): ?array
    {
        foreach ($this->regions as $r) if ($r['code']===$code) return $r;
        return null;
    }
}
