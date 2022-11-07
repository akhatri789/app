<?php

namespace App\Http\Controllers\api\v1;

use App\CPU\CategoryManager;
use App\CPU\Helpers;
use App\CPU\ImageManager;
use App\CPU\ProductManager;
use App\Http\Controllers\Controller;
use App\Model\Category;
use App\Model\OrderDetail;
use App\Model\Product;
use App\Model\Review;
use App\Model\ShippingMethod;
use App\Model\Wishlist;
use App\Model\Color;
use App\Model\FlashDeal;
use App\Model\FlashDealProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use function App\CPU\translate;

class ProductController extends Controller
{
    public function get_latest_products(Request $request)
    {
        $products = ProductManager::get_latest_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_featured_products(Request $request)
    {
        $products = ProductManager::get_featured_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_top_rated_products(Request $request)
    {
        $products = ProductManager::get_top_rated_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_searched_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $products = ProductManager::search_products($request['name'], $request['limit'], $request['offset']);
        if ($products['products'] == null) {
            $products = ProductManager::translated_product_search($request['name'], $request['limit'], $request['offset']);
        }
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }

    public function get_product($slug)
    {
        $product = Product::with(['reviews.customer'])->where(['slug' => $slug])->first();
        if (isset($product)) {
            $product = Helpers::product_data_formatting($product, false);
        }
        return response()->json($product, 200);
    }

    public function get_best_sellings(Request $request)
    {
        $products = ProductManager::get_best_selling_products($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        
        return response()->json($products, 200);
    }

    public function get_home_categories()
    {
        $categories = Category::where('home_status', true)->get();
        $categories->map(function ($data) {
            $data['products'] = Helpers::product_data_formatting(CategoryManager::products($data['id']), true);
            return $data;
        });
        return response()->json($categories, 200);
    }

    public function get_related_products($id)
    {
        if (Product::find($id)) {
            $products = ProductManager::get_related_products($id);
            $products = Helpers::product_data_formatting($products, true);
            return response()->json($products, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('Product not found!')]
        ], 404);
    }

    public function get_product_reviews($id)
    {
        $reviews = Review::with(['customer'])->where(['product_id' => $id])->get();

        $storage = [];
        foreach ($reviews as $item) {
            $item['attachment'] = json_decode($item['attachment']);
            array_push($storage, $item);
        }

        return response()->json($storage, 200);
    }

    public function get_product_rating($id)
    {
        try {
            $product = Product::find($id);
            $overallRating = \App\CPU\ProductManager::get_overall_rating($product->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function counter($product_id)
    {
        try {
            $countOrder = OrderDetail::where('product_id', $product_id)->count();
            $countWishlist = Wishlist::where('product_id', $product_id)->count();
            return response()->json(['order_count' => $countOrder, 'wishlist_count' => $countWishlist], 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function social_share_link($product_slug)
    {
        $product = Product::where('slug', $product_slug)->first();
        $link = route('product', $product->slug);
        try {

            return response()->json($link, 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    public function submit_product_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
            'comment' => 'required',
            'rating' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image_array = [];
        if (!empty($request->file('fileUpload'))) {
            foreach ($request->file('fileUpload') as $image) {
                if ($image != null) {
                    array_push($image_array, ImageManager::upload('review/', 'png', $image));
                }
            }
        }

        $review = new Review;
        $review->customer_id = $request->user()->id;
        $review->product_id = $request->product_id;
        $review->comment = $request->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($image_array);
        $review->save();

        return response()->json(['message' => translate('successfully review submitted!')], 200);
    }

    public function get_shipping_methods(Request $request)
    {
        $methods = ShippingMethod::where(['status' => 1])->get();
        return response()->json($methods, 200);
    }

    public function get_discounted_product(Request $request)
    {
        $products = ProductManager::get_discounted_product($request['limit'], $request['offset']);
        $products['products'] = Helpers::product_data_formatting($products['products'], true);
        return response()->json($products, 200);
    }
    
    public function get_product_with_filter(Request $request){
        $request['sort_by'] == null ? $request['sort_by'] == 'latest' : $request['sort_by'];
        $porduct_data = Product::active()->with(['reviews']);

        if ($request['data_from'] == 'category') {
            /*$products = $porduct_data->get();
            $product_ids = [];
            foreach ($products as $product) {
                $categoryIds = [];
                foreach (json_decode($product['category_ids'], true) as $category) {
                    array_push($categoryIds,$category['id']);
                }
                $categoryIds = Category::select('id')->whereIn('id',$categoryIds)->get()->toArray();
                foreach($categorySlugs as $slug){
                    if ($slug['id'] == $request['id']) {
                        array_push($product_ids, $product['id']);
                    }
                }
            }
            $query = $porduct_data->whereIn('id', $product_ids);*/
            $query = $porduct_data->where('category_ids','like','%"id":"'.$request['id'].'"%');
        }

        if ($request['data_from'] == 'brand') {
            $query = $porduct_data->where('brand_id', $request['id']);
        }

        if ($request['data_from'] == 'latest') {
            $query = $porduct_data;
        }

        if ($request['data_from'] == 'top-rated') {
            $reviews = Review::select('product_id', DB::raw('AVG(rating) as count'))
                ->groupBy('product_id')
                ->orderBy("count", 'desc')->get();
            $product_ids = [];
            foreach ($reviews as $review) {
                array_push($product_ids, $review['product_id']);
            }
            $query = $porduct_data->whereIn('id', $product_ids);
        }

        if ($request['data_from'] == 'best-selling') {
            $details = OrderDetail::with('product')
                ->select('product_id', DB::raw('COUNT(product_id) as count'))
                ->groupBy('product_id')
                ->orderBy("count", 'desc')
                ->get();
            $product_ids = [];
            foreach ($details as $detail) {
                array_push($product_ids, $detail['product_id']);
            }
            $query = $porduct_data->whereIn('id', $product_ids);
        }

        if ($request['data_from'] == 'most-favorite') {
            $details = Wishlist::with('product')
                ->select('product_id', DB::raw('COUNT(product_id) as count'))
                ->groupBy('product_id')
                ->orderBy("count", 'desc')
                ->get();
            $product_ids = [];
            foreach ($details as $detail) {
                array_push($product_ids, $detail['product_id']);
            }
            $query = $porduct_data->whereIn('id', $product_ids);
        }

        if ($request['data_from'] == 'featured') {
            // old query $query = Product::with(['reviews'])->active()->where('featured', 1);
             $query =Product::with(['rating'])->active()
            ->where('featured', 1)
            ->withCount(['order_details'])->orderBy('order_details_count', 'DESC');
        }

        if ($request['data_from'] == 'featured_deal') {
            $featured_deal_id = FlashDeal::where(['status'=>1])->where(['deal_type'=>'feature_deal'])->pluck('id')->first();
            $featured_deal_product_ids = FlashDealProduct::where('flash_deal_id',$featured_deal_id)->pluck('product_id')->toArray();
            $query = Product::with(['reviews'])->active()->whereIn('id', $featured_deal_product_ids);
        }

        if ($request['data_from'] == 'search') {
            $key = explode(' ', $request['name']);
            $product_ids = Product::where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            })->pluck('id');

            if($product_ids->count()==0)
            {
                $product_ids = Translation::where('translationable_type', 'App\Model\Product')
                    ->where('key', 'name')
                    ->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('value', 'like', "%{$value}%");
                        }
                    })
                    ->pluck('translationable_id');


            }

            $query = $porduct_data->WhereIn('id', $product_ids);

        }

        if ($request['data_from'] == 'discounted') {
            $query = Product::with(['reviews'])->active()->where('discount', '!=', 0);
        }

        $fetched = isset($query) ? $query : $porduct_data;
        if ($request['price_range'] != null) {
            $price_range= explode(",",$request['price_range']);
            $fetched->where(function($query) use ($price_range) {
                $i =0;
                foreach($price_range as $pricerange){
                    if($pricerange != ">10000"){
                        $prices = explode("-",$pricerange);
                        if($i == 0){
                            $query->whereBetween('after_discount_price', [Helpers::convert_currency_to_usd($prices[0]), Helpers::convert_currency_to_usd($prices[1])]);
                        }else{
                            $query->orWhereBetween('after_discount_price', [Helpers::convert_currency_to_usd($prices[0]), Helpers::convert_currency_to_usd($prices[1])]);
                        }
                    }else{
                        if($i == 0){
                            $query->where('after_discount_price', '>',Helpers::convert_currency_to_usd(10000));
                        }else{
                            $query->orWhere('after_discount_price', '>', Helpers::convert_currency_to_usd(10000));
                        }
                    }
                    $i++;
                }
            });
           // $fetched = $fetched->whereBetween('unit_price', [Helpers::convert_currency_to_usd($request['min_price']), Helpers::convert_currency_to_usd($request['max_price'])]);
        }
        if ($request['color_range'] != null) {
            $color_range= explode(",",$request['color_range']);
            $fetched->where(function($query) use ($color_range) {
                $i =0;
                foreach($color_range as $colorrange){
                    if($i == 0){
                        $query->whereJsonContains('colors',$colorrange);
                    }else{
                        $query->orWhereJsonContains('colors',$colorrange);
                    }
                    $i++;
                }
            });
        }
        
        if ($request['variants'] != null) {
            $variants= json_decode($request['variants']);
            if($variants && count($variants)){
                $variants = (array)$variants;
                $productIds = [];
                $variantList = Product::select('choice_options','id')->where('choice_options','<>','')->get()->toArray();
                foreach($variantList as $vl){
                    $val = $vl['choice_options'] ? json_decode($vl['choice_options']) : [];
                    foreach($val as $multvari){
                        $multvari = (array)$multvari;
                        if(array_key_exists('title',$multvari) && array_key_exists('options',$multvari) && is_array($multvari['options'])){
                            foreach($variants as $vrnts){
                                $vrnts= (array)$vrnts;
                                $multvari['options'] = (array)$multvari['options'];
                              if($multvari['title'] == $vrnts['variant'] && in_array($vrnts['value'],$multvari['options'])){
                                 array_push($productIds,$vl['id']); 
                              }   
                            }
                        }
                    }
                }
                $fetched = $fetched->WhereIn('id', $productIds);
            }
        }

        if ($request['sort_by'] == 'latest') {
            $fetched = $fetched->latest();
        } elseif ($request['sort_by'] == 'low-high') {
            $fetched = $fetched->orderBy('unit_price', 'ASC');
        } elseif ($request['sort_by'] == 'high-low') {
            $fetched = $fetched->orderBy('unit_price', 'DESC');
        } elseif ($request['sort_by'] == 'a-z') {
            $fetched = $fetched->orderBy('name', 'ASC');
        } elseif ($request['sort_by'] == 'z-a') {
            $fetched = $fetched->orderBy('name', 'DESC');
        } else {
            $fetched = $fetched->latest();
        }
        
        /*$data = [
            'slug' => $request['slug'],
            'name' => $request['name'],
            'data_from' => $request['data_from'],
            'sort_by' => $request['sort_by'],
            'page_no' => $request['page'],
            'price_range' => $request['price_range'],
            'color_range'=>$request['color_range'],
            'variants'=>$request['variants']
        ];*/
        $skip = $request['limit'] * ($request['page'] - 1);
        $products = $fetched;
        $last_query = DB::getQueryLog();
        
         return [
            'total_size' => $products->count(),
            'limit' => (int)$request['limit'],
            'offset' => (int)$request['page'],
            'products' => Helpers::product_data_formatting($products->skip($skip)->take($request['limit'])->get(), true)
        ];
    }
    
    public function get_product_filter_list(){
        $data = [];
        
        // For get color list
        $colorsArr = Product::select('colors')->where('colors','<>','')->get()->toArray();
        $colorListArr = array();
        foreach($colorsArr as $clr){
           $colorListArr = array_merge($colorListArr,json_decode($clr['colors']));    
        }
        $colorListArr = array_unique($colorListArr);
        $colorList = Color::select('code','name','value')->whereIn('code',$colorListArr)->get()->toArray();
        
        // For get choice list (variants)
        $variantList = Product::select('choice_options')->where('choice_options','<>','')->get()->toArray();
        $variants = array();
        
		foreach($variantList as $vl){
            $val = $vl['choice_options'] ? json_decode($vl['choice_options']) : [];
            foreach($val as $multvari){
                $multvari = (array)$multvari;
                if(array_key_exists('title',$multvari) && array_key_exists('options',$multvari) && is_array($multvari['options'])){
                    if(array_key_exists($multvari['title'],$variants))
                    {
                        for($i= 0; $i < count($multvari['options']); $i++){
                            $newItem = trim($multvari['options'][$i]);
                            if(!in_array($newItem,$variants[$multvari['title']])){
                                array_push($variants[$multvari['title']],$newItem);         
                           }
                        }
                    }
                    else 
                    {
						$variants[$multvari['title']] = array();
                        for($i=0;$i<count($multvari['options']);$i++){
                            $newItem = trim($multvari['options'][$i]);
                            if(!in_array($newItem,$variants[$multvari['title']]))
                            {
                                array_push($variants[$multvari['title']],$newItem);         
                            }
                        }
                    }
                }
            }
        }
        
        // For price list
        $priceList = array(
            "title" => "Price",
            "listData"=>array( 
            array('name'=>"0-499",'code'=>'','value'=> '0-499'),
            array('name'=>"500-999",'code'=>'','value'=> '500-999'),
            array('name'=>"1000-1999",'code'=>'','value'=> '1000-1999'),
            array('name'=>"2000-4999",'code'=>'','value'=> '2000-4999'),
            array('name'=>"5000-9999",'code'=>'','value'=> '5000-9999'),
            array('name'=>"More Then 10000",'code'=>'','value'=> '>10000')
            )
        );
        array_push($data,$priceList);
        array_push($data,array('title'=>'Color','listData'=>$colorList));
        
        $variantsdata = array();
        foreach($variants as $key=>$value){
            array_push($variantsdata,array("title"=>$key,"list"=>$value));
        }
        
        // For category list
        $categoryList = array(
            "title"=>"Category",
            "listData"=>array()
        );
        $categorys = DB::table('categories')->select('id as value','name')->where('parent_id',0)->get()->toArray();
        $i = 0;
        foreach($categorys as $ctgry){
            $categorys[$i++]->code = "";
        }
        $categoryList['listData'] = $categorys;
        array_push($data,$categoryList);
        return [
            'variants' => $variantsdata,
            'data' => $data,
        ];
    }
}
