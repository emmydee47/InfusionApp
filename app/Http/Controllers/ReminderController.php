<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function assignReminder(Request $request)
    {
        $contactMail = $request->contact_email;
        $this->getAllTags();
    }

    private function getAllTags()
    {
        $infusionsoft = new InfusionsoftHelper();
       // $allTags = $infusionsoft->getAllTags();
        $allTags = $infusionsoft->getContact("Groups");
        Log::info(json_encode($allTags));
        return response()->json($allTags);
    }

}
