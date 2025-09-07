<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class NumistrModelMints extends BaseDatabaseModel
{
    public function signatureFromInput($input): string
    {
        return implode('|', [
            $input->getString('filter.region',''),
            $input->getString('q',''),
            $input->getString('page','1'),
            $input->getString('per_page','20'),
            $input->getString('sort','name')
        ]);
    }

    public function fetchList($input): array
    {
        // TODO: Replace with real source.
        $items = [[ 'slug'=>'kyzikos','name'=>'Kyzikos','region'=>'mysia','lat'=>40.388,'lng'=>27.872,'coins_count'=>128 ]];
        $meta  = ['total'=>1,'page'=>1,'per_page'=>20];
        $links = ['next'=>null,'prev'=>null];
        return [$items,$meta,$links];
    }

    public function fetchOne(string $slug): ?array
    {
        // TODO: Replace with real source.
        if ($slug !== 'kyzikos') return null;
        return [ 'slug'=>'kyzikos','name'=>'Kyzikos','region'=>'mysia','lat'=>40.388,'lng'=>27.872,'desc'=>null ];
    }
}
