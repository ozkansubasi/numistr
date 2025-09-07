<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class NumistrModelVariants extends BaseDatabaseModel
{
    // No SQL here — only param parsing & dummy data.

    public function signatureFromInput($input): string
    {
        $parts = [
            $input->getString('filter.region',''),
            $input->getString('filter.mint',''),
            $input->getString('filter.metal',''),
            $input->getString('filter.date.from',''),
            $input->getString('filter.date.to',''),
            $input->getString('filter.weight.gte',''),
            $input->getString('filter.weight.lte',''),
            $input->getString('filter.diameter.gte',''),
            $input->getString('filter.diameter.lte',''),
            $input->getString('q',''),
            $input->getString('page','1'),
            $input->getString('per_page','20'),
            $input->getString('sort','uid'),
            $input->getString('fields',''),
            $input->getString('include',''),
            $input->getString('updated_after','')
        ];
        return implode('|',$parts);
    }

    public function fetchList($input): array
    {
        // TODO: Replace with real fetching.
        $page = max(1, (int)$input->get('page', 1));
        $per  = min(100, max(1, (int)$input->get('per_page', 20)));

        $items = [[
            'uid' => 'ntr:var:DEMO1',
            'slug'=> 'demo-variant',
            'title'=> 'Demo Variant',
            'metal'=> 'AR',
            'date_from'=> -200, 'date_to'=> -150,
            'mint'=> ['slug'=>'kyzikos','name'=>'Kyzikos','region'=>'mysia'],
            'images'=> ['thumb'=>'https://example.org/i/demo_t.webp'] // filigranlı thumb
        ]];

        $meta  = ['total'=>1, 'page'=>$page, 'per_page'=>$per];
        $links = ['next'=>null, 'prev'=>null];
        return [$items, $meta, $links];
    }

    public function fetchOne(string $uid): ?array
    {
        // TODO: Replace with real fetching.
        if ($uid !== 'ntr:var:DEMO1') return null;
        return [
            'uid'=>'ntr:var:DEMO1','slug'=>'demo-variant','title'=>'Demo Variant',
            'metal'=>'AR','date_from'=>-200,'date_to'=>-150,
            'nominal_specs'=>['weight_nominal'=>3.20,'diameter_nominal'=>18.0,'die_axis'=>6],
            'mint'=>['slug'=>'kyzikos','name'=>'Kyzikos','region'=>'mysia'],
            'images'=>[['id'=>12345,'role'=>'obv','thumb'=>'https://example.org/i/demo_t.webp']]
        ];
    }

    public function serializeList(array $rows, string $fieldsCsv, string $includeCsv): array
    {
        $fields  = $this->csv($fieldsCsv);
        $include = $this->csv($includeCsv);

        return array_map(function($r) use ($fields, $include){
            $out = [
                'uid'   => $r['uid'] ?? null,
                'slug'  => $r['slug'] ?? null,
                'title' => $r['title'] ?? null,
                'mint'  => in_array('mint',$include) ? ($r['mint'] ?? null) : null,
                'images'=> in_array('images',$include) ? ($r['images'] ?? null) : null,
            ];
            return $fields ? array_intersect_key($out, array_flip($fields)) : array_filter($out, fn($v)=>$v!==null);
        }, $rows);
    }

    public function serializeOne(array $r, string $fieldsCsv, string $includeCsv): array
    {
        $fields  = $this->csv($fieldsCsv);
        $include = $this->csv($includeCsv);

        $out = [
            'uid'   => $r['uid'] ?? null,
            'slug'  => $r['slug'] ?? null,
            'title' => $r['title'] ?? null,
            'metal' => $r['metal'] ?? null,
            'date_from' => $r['date_from'] ?? null,
            'date_to'   => $r['date_to'] ?? null,
            'nominal_specs' => $r['nominal_specs'] ?? null,
            'mint'   => in_array('mint',$include) ? ($r['mint'] ?? null) : null,
            'images' => in_array('images',$include) ? ($r['images'] ?? null) : null,
        ];

        return $fields ? array_intersect_key($out, array_flip($fields)) : array_filter($out, fn($v)=>$v!==null);
    }

    private function csv(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }
}
