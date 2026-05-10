<?php declare(strict_types=1);

namespace App\YoutubeDl;

use YoutubeDl\YoutubeDl;

class YoutubeDlFactory
{
    public function create(): YoutubeDl
    {
        return new YoutubeDl();
    }
}
