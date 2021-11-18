<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class group_monthly_scores extends Model
{
    //we're logging daily group scores, so we can once a day, run a method to fetch all 
    //THIS months group scores, sort them and select the middle one as this months group score.
    
    //fetch all THIS months group scores
    
    //sort them the same way we do in pinescore original project 
    //select the middle one the same as we do in pinescore priginal project
    //insert into new table
}
