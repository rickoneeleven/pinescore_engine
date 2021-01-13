<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class oscheck extends Model
{
    public function isLinux() {
        if(PHP_OS == "Linux") return true;
        return false;
    }
}
