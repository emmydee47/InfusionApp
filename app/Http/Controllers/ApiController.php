<?php

namespace App\Http\Controllers;

use App\Http\Helpers\InfusionsoftHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\User;
use App\Module;
use Response;

class ApiController extends Controller
{
    // Todo: Module reminder assigner

    private function exampleCustomer(){

        $infusionsoft = new InfusionsoftHelper();
        ReminderController::init();

        $uniqid = uniqid();

        $infusionsoft->createContact([
            'Email' => $uniqid.'@test.com',
            "_Products" => 'ipa,iea'
        ]);

        $user = User::create([
            'name' => 'Test ' . $uniqid,
            'email' => $uniqid.'@test.com',
            'password' => bcrypt($uniqid)
        ]);

        // attach IPA M1-3 & M5
        $user->completed_modules()->attach(Module::where('course_key', 'ipa')->limit(3)->get());
        $user->completed_modules()->attach(Module::where('name', 'IPA Module 5')->first());


        return $user;
    }

    public function assignReminder(Request $request)
    {
        $contactMail = $request->contact_email;

        if(!$contactMail)                        // request for email address if not sent
        {
            return response()->json(["message"=>"Please add email address"], 204); 
        }

        $infusionsoft = new InfusionsoftHelper();        
        $userCount =  DB::table('users')->get()->count();        // check if database have sample data else generate sample data
        $user = array();
        if($userCount==0)
        {
           $user = $this->exampleCustomer();
        }

        
        $userContact = $infusionsoft->getContact($contactMail);  // check if contact exists on infusion
        if(!$userContact)
        {
            return response()->json(["message"=>"contact email does not exist, please use this email: ".$user->email], 400); 
        }

        $userProducts = explode(",", $userContact['_Products']); // store products in an array
        $userContactID = $userContact['Id'];
        
        $tag = $this->processAssignment($contactMail, $userProducts);
        $tagId = $this->getTagId($tag);                        // get module reminder tag
        $tagResponse = $this->addTag($userContactID, $tagId);  // add tag to contact id
        return response()->json($tagResponse, 200);             
    }

    public function processAssignment($contactMail, $userProducts)
    {
        $productCount = count($userProducts);
        $count = 0;
        
        foreach($userProducts as $courseKey)  // loop through products and check for modules
        {
            $count++;
            $nextModule = [];
            $lastCompletedModule = $this->getLastCompletedModule($contactMail, $courseKey );
            
            if($lastCompletedModule!=[])    // if user has a completed module, increment the module id and check for the next module
            {
                $moduleId = $lastCompletedModule->module_id + 1;
                $nextModule = $this->getNextModule($moduleId);
               
                if($nextModule==[])         // if there is no next module for the course move to the next course
                {
                    continue;
                }
                               
                if(strtolower($nextModule->course_key)==strtolower($courseKey))  // check if the module belongs to the existing course 
                {
                    $tag = "Start ".$nextModule->name." Reminders";
                    return $tag;
                }

                if($count==$productCount)   // if at the end of the loop there are no modules belonging to the course, send completion message
                {
                    $tag = "Module reminders completed";
                    return $tag;
                }
               
            }elseif($lastCompletedModule==[])
            {
                $nextModule = $this->getNextCourseFirstModule($courseKey);    // if no module has been taken under the current course, add the first course to user
                $tag = "Start ".$nextModule->name." Reminders";                
                return $tag;
            }
        }
    }
   
    private function addTag($userContactID, $tagId)      // function to append tag to user
    {
        $infusionsoft = new InfusionsoftHelper();
        if($tagId==0){           
            $response = array("success"=>false, "message"=>"Module reminders completed");                                        
            return response()->json($response);
        }
        
        $addTag = $infusionsoft->addTag($userContactID, $tagId);
        $response = array("success"=>false, "message"=>"Reminder not sent"); 

        if($addTag==1){
            $response = array("success"=>true, "message"=>"Reminder sent successfully");
        } 
                                               
        return response()->json($response);
    }

    
    private function getLastCompletedModule($userEmail, $courseKey)   // function to get the last module taken for the current course by user
    {
        $userLastCompletedModule =  DB::table('user_completed_modules')
                                    ->join('users', 'user_completed_modules.user_id', '=', 'users.id')
                                    ->join('modules', 'user_completed_modules.module_id', '=', 'modules.id')
                                    ->select('user_completed_modules.user_id', 'user_completed_modules.module_id', 'modules.course_key', 'modules.name')
                                    ->where([
                                        ['users.email', '=', $userEmail],
                                        ['modules.course_key', '=', $courseKey],
                                    ])
                                    ->orderBy('user_completed_modules.module_id', 'asc')
                                    ->get()
                                    ->last();                            
        return $userLastCompletedModule;
    }
    
    private function getNextModule($moduleId)     // function to get the next module to remind the user of
    { 
        $modules =  DB::table('modules')
                    ->find($moduleId);
        return $modules;
    }
    
    private function getNextCourseFirstModule($courseKey)   // function to get the first module of a new course
    { 
        $modules =  DB::table('modules')
                    ->where('course_key', '=', $courseKey)
                    ->get()
                    ->first(); 
        return $modules;
    }

    

    private function getTagId($tag)      // function to get TagID
    {
        $modules =  DB::table('reminder_tags')
                    ->where('name', '=', $tag)
                    ->get()
                    ->first();   
        return $modules->id;
    }
}
