<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\other;

    class partytest extends Controller
    {
        public function index() {
            $other = other::find(1);
            echo $other['value'];
        }
    }
