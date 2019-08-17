<?php

namespace App\Http\Controllers;

use App\Http\Helpers\InfusionsoftHelper;
use App\ReminderTags;
use App\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Response;

class reminderController extends Controller
{
    public static function init()
    {
        $_this = new self;
        $reminderTagsCount =  DB::table('reminder_tags')->get()->count();
        
        if($reminderTagsCount==0)
        {
            $_this->getAllTags();
        }
        
    }

    private function getAllTags()
    {
        $_this = new self;
        $infusionsoft = new InfusionsoftHelper();
        $allTags = $infusionsoft->getAllTags();    
        $allTagsArray = json_decode($allTags, true);
        
        try{
            foreach($allTagsArray as $tags)
            {
                $_this->saveReminderTags($tags);
            }
        }catch(Exception $e){
            Log::info('Error Log: '.$e);
        }
        
    }

    private function saveReminderTags($tags)
    {
        $_this = new self;
        $reminderTags = new ReminderTags();
        $reminderTags->id = $tags['id'];
        $reminderTags->name = $tags['name'];
        $reminderTags->description = $tags['description'];
        if(is_array($tags['category']))
        {
            $reminderTags->category = " ";
                        
        }else{
            $reminderTags->category = $tags['category'];
            if(!is_null($tags['description'])){
                $_this->saveModule($tags['name']);
            }
            
        }
        
        $reminderTags->save();
    }

    private function saveModule($name)
    {
        $moduleModel = new Module();
        $nameArray = explode(" ",$name);
        $moduleModel->course_key = $nameArray[1];
        $moduleModel->name = $nameArray[1]." ".$nameArray[2]." ".$nameArray[3];
        $moduleModel->save();
    }

}
