<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\User;
use App\Model\DeliveryStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Brian2694\Toastr\Facades\Toastr;

class DeliveryStatusController extends Controller
{
    public function index(Request $request){
        $data['title'] = "Delivery Status";
        $query_param = [];
        $search = $request['search'];
        if ($request->has('search')) {
            $key =  $request['search'];
            $delivery_status = DeliveryStatus::where(function ($q)use($key)  {
                $q->where('zip_code', 'like', "%{$key}%")
                ->orWhere('status', 'like', "%{$key}%")
                ->orWhere('no_of_day', 'like', "%{$key}%");
                
            });
            $query_param = ['search' => $request['search']];
        } else {
            $delivery_status = new DeliveryStatus();
        }

        $delivery_status = $delivery_status->latest()->paginate(25)->appends($query_param);
        return view('admin-views.delivery-status.index', compact('data','delivery_status','search'));
    }
    
    public function create(Request $request){
        $request->validate([
            'zip_code' => 'required|unique:delivery_status,zip_code',
            'no_of_day' => 'required'
        ], [
            'zip_code.required' => 'Zip Code is required!',
            'no_of_day.required' => 'No Of Day is required!',
        ]);

        $dm = new DeliveryStatus();
        $dm->zip_code = $request->zip_code;
        $dm->no_of_day = $request->no_of_day;
        $dm->status = array_key_exists('status',$request->all()) ? 1 : 0;
        $dm->save();

        Toastr::success('Delivery status added successfully!');
        return redirect('admin/business-settings/delivery-status');
    
    }
    
    public function edit(Request $request,$id){
        $request->validate([
            'zip_code' => 'required',
            'no_of_day' => 'required',
            'status' => 'required',
        ], [
            'zip_code.required' => 'Zip Code is required!',
            'no_of_day.required' => 'No Of Day is required!',
        ]);
       
        $delivery_status = DeliveryStatus::where(['id' => $id])->first();
        
        $delivery_status->zip_code = $request->zip_code;
        $delivery_status->no_of_day = $request->no_of_day;
        $delivery_status->status = $request->status;
        $delivery_status->save();
    
        Toastr::success('Delivery status updated successfully!');
        return redirect('admin/business-settings/delivery-status');
    }
    
    public function delete(Request $request,$id){
        DeliveryStatus::where(['id' => $id])->delete();
        Toastr::success('Delivery status removed!');
        return redirect('admin/business-settings/delivery-status');
    }
    
    public function updateStatus(Request $request){
        DB::table('delivery_status')->where('id',$request->id)->update(['status'=>$request->status]);
        return response()->json([], 200);
    }
}