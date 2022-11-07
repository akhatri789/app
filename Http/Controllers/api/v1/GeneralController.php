<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Model\HelpTopic;
use App\CPU\Helpers;

class GeneralController extends Controller
{
    public function faq(){
        return response()->json(HelpTopic::orderBy('ranking')->get(),200);
    }
    
    public function getConvenienceFee(){
        $data = Helpers::get_business_settings('convenience_fee');
        $data = $data ? (array)$data : [];
        return response()->json($data,200);
    }
}
