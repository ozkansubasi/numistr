<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class NumistrModelFeed extends BaseDatabaseModel
{
    public function fetch(): array
    {
        // TODO: Replace with real sources (news, daily coin, highlights).
        return [
            'news' => [
                ['title'=>'Demo News','url'=>'https://www.numistr.org/blog/demo','published_at'=>'2025-09-01T00:00:00Z']
            ],
            'daily_coin' => ['variant_uid'=>'ntr:var:DEMO1','thumb_url'=>'https://example.org/i/demo_t.webp'],
            'highlights' => []
        ];
    }
}
