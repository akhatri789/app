<?php

namespace App\Http\Controllers\api\v1;

use Illuminate\Http\Request;
use App\CPU\CategoryManager;
use App\CPU\Helpers;
use App\Http\Controllers\Controller;
use App\Model\Category;
use App\Model\Product;
use DB;

class CategoryController extends Controller
{
    public function get_categories()
    {
        try {
            $categories = Category::with(['childes.childes'])->where(['position' => 0])->priority()->get();
            return response()->json($categories, 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

    public function get_products(Request $request,$id)
    {
       //$products = Product::whereRaw("JSON_EXTRACT(category_ids,'$.id') ='$id' ")->skip($request['offset']-1)->take($request['limit'])->get();
       // $products = Product::selectRaw("JSON_EXTRACT(json_details,"$[*].id") as id")->whereRaw("JSON_CONTAINS(JSON_EXTRACT(json_details,"$[*].id"),"1","$") ")->skip($request['offset']-1)->take($request['limit'])->get();
       // $products = Product::whereRaw("json_extract('category_ids', '$.id')", '=', $id)->skip($request['offset']-1)->take($request['limit'])->get();
       // $products = Product::where('category_ids->id',$id)->skip($request['offset']-1)->take($request['limit'])->get();
       // return response()->json(Helpers::product_data_formatting($products, true), 200);
       //return response()->json(Helpers::product_data_formatting(CategoryManager::products($id), true), 200);
       $skip = $request['limit'] * ($request['offset'] - 1);
       $products = Product::where('category_ids','like','%"id":"'.$id.'"%')->skip($skip)->take($request['limit']);
       
       
       
       $data = array();
       $data["total_size"] = $products->count();
       $data["limit"] = $request['limit'];
       $data["offset"] = $request['offset'];
       $data["products"] = Helpers::product_data_formatting($products->get(),true); 
       
       
       return response()->json($data, 200);
    }
}
