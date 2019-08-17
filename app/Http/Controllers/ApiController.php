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

        // request for email address if not sent
        if(!$contactMail)
        {
            return response()->json(["message"=>"Please add email address"], 200); 
        }

        $infusionsoft = new InfusionsoftHelper();
        // check if database have sample data else generate sample data
        $userCount =  DB::table('users')->get()->count();        
        if($userCount==0)
        {
            $this->exampleCustomer();
        }

        // check if contact exists on infusion
        $userContact = $infusionsoft->getContact($contactMail);
        if(!$userContact)
        {
            return response()->json(["message"=>"contact email does not exist"], 400); 
        }

        // store products in an array
        $userProducts = explode(",", $userContact['_Products']);
        $userContactID = $userContact['Id'];
        $productCount = count($userProducts);
        $count = 0;

        // loop through products and check for modules
        foreach($userProducts as $courseKey)
        {
            $count++;
            $nextModule = [];
            $lastCompletedModule = $this->getLastCompletedModule($contactMail, $courseKey );
            
            // if user has a completed module, increment the module id and check for the next module
            if($lastCompletedModule!=[])
            {
                $moduleId = $lastCompletedModule->module_id + 1;
                $nextModule = $this->getNextModule($moduleId);

                // if there is no next module for the course move to the next course
                if($nextModule==[])
                {
                    continue;
                }
                
                // check if the module belongs to the existing course 
                if(strtolower($nextModule->course_key)==strtolower($courseKey))
                {
                    $tag = "Start ".$nextModule->name." Reminders";
                    // get module reminder tag
                    $tagId = $this->getTagId($tag);

                    // add tag to contact id
                    $tagResponse = $this->addTag($userContactID, $tagId);
                    return response()->json($tagResponse, 200);
                }

                // if at the end of the loop there are no modules belonging to the course, send completion message
                if($count==$productCount)
                {
                    $tag = "Module reminders completed";
                    
                    $tagId = $this->getTagId($tag);
                    $tagResponse = $this->addTag($userContactID, $tagId);
                    return response()->json($tagResponse, 200);
                }
               
            }elseif($lastCompletedModule==[]){
                // if no module has been taken under the current course, add the first course to user
                $nextModule = $this->getNextCourseFirstModule($courseKey);
                $tag = "Start ".$nextModule->name." Reminders";
                
                $tagId = $this->getTagId($tag);
                $tagResponse = $this->addTag($userContactID, $tagId);
                return response()->json($tagResponse, 200);
            }
        }
             
    }
   // function to append tag to user
    private function addTag($userContactID, $tagId)
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

    // function to get the last module taken for the current course by user
    private function getLastCompletedModule($userEmail, $courseKey)
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
    // function to get the next module to remind the user of
    private function getNextModule($moduleId)
    { 
        $modules =  DB::table('modules')
                    ->find($moduleId);
        return $modules;

    }
    // function to get the first module of a new course
    private function getNextCourseFirstModule($courseKey)
    { 
        $modules =  DB::table('modules')
                    ->where('course_key', '=', $courseKey)
                    ->get()
                    ->first(); 
        return $modules;

    }

    // function to get TagID

    private function getTagId($tag)
    {
        $modules =  DB::table('reminder_tags')
                    ->where('name', '=', $tag)
                    ->get()
                    ->first();   
        return $modules->id;
    }
}
