<?php


namespace App\Http\Controllers;

use App\UserInfo;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use App\GoodCat;
use App\GoodInfo;
use App\GoodTag;
use App\Transaction;
use App\TransactionLog;
use App\Message;
use App\Tag;
use App\FavList;
use App\Http\Controllers\Controller;
use Storage;
use Image;
use App\Http\Requests\AddGoodRequest;
use App\Http\Requests\EditGoodRequest;
use Mews\Purifier\Purifier;

class GoodController extends Controller
{
    /**
     * @function GoodController@getList
     * @input Request $request
     * @return View
     * @description Get the list of all goods.
     */
    public function getList(Request $request)
    {
        $paginate_limit = 18;
        $data = [];
        $input = $request->all();
        $query = "";
        $cat_id = $start_price = $end_price = $start_count = $sort = "";
        if(isset($input['cat_id']))
            $cat_id = $input['cat_id'];
        if(isset($input['start_price']))
            $start_price = $input['start_price'];
        if(isset($input['end_price']))
            $end_price = $input['end_price'];
        if(isset($input['start_count']))
            $start_count = $input['start_count'];
        if(isset($input['sort']))
            $sort = $input['sort'];
        $data['goods'] = GoodInfo::where('good_name', 'like', "%$query%");
        //关键词搜索：多个关键词以空格分隔，默认排除以减号开头的关键词后，必须包含所有剩下的关键词
        if(isset($input['query'])){
            //将查询内容以一个或多个空格为界分隔开
            $query = $input['query'];
            $keywords = explode(' ', $query);
            $keywords = array_filter($keywords);
            foreach($keywords as $index=>$keyword){
                if($keyword[0] == '-'){
                    $keyword = ltrim($keyword, '-');
                    $wheredata[$index] = array('good_name', 'not like', "%$keyword%");
                }
                else{
                    $wheredata[$index] = array('good_name', 'like', "%$keyword%");
                }
            }
            if(count($keywords) > 0)
                $data['goods'] = $data['goods']->where($wheredata);
        }
        //$data['cat_id'] = 0;
        if(isset($input['cat_id']) && $input['cat_id'] != ""){
            $data['goods'] = GoodInfo::where('cat_id', $input['cat_id']);
            $data['cat_id'] = $input['cat_id'];
        }
        //同时给定价格上下限
        if(isset($input['start_price']) && isset($input['end_price'])
            && $input['start_price'] != "" && $input['end_price'] != ""){
            //确保上限大于下限
            if($input['start_price'] > $input['end_price']){
                $temp = $input['start_price'];
                $input['start_price'] = $input['end_price'];
                $input['end_price'] = $temp;
            }
            $data['goods'] = $data['goods']->where([
                ['price', '>=', $input['start_price']],
                ['price', '<=', $input['end_price']]
            ]);
        }
        //只设上限或下限时
        else{
            if(isset($input['start_price']) && $input['start_price'] != "")
                $data['goods'] = $data['goods']->where('price', '>=', $input['start_price']);
            if(isset($input['end_price']) && $input['end_price'] != "")
                $data['goods'] = $data['goods']->where('price', '<=', $input['end_price']);
        }
        //对数量筛选只设下限
        if(isset($input['start_count']) && filter_var($input['start_count'], FILTER_VALIDATE_INT))
            $data['goods'] = $data['goods']->where('count', '>=', $input['start_count']);
        else
            $data['goods'] = $data['goods']->where('count', '>', 0);

        // 最后在排序筛选baned==0以及paginate分页
        // p为价格，c为数量，d为倒序，值相同时按id倒序排列
        if(isset($input['sort']) && $input['sort'] != ""){
            if ($input['sort'] == 'old'){
                $data['goods'] = $data['goods']->orderby('id', 'asc');
            }
            elseif ($input['sort'] == 'p'){
                $data['goods'] = $data['goods']->orderby('price', 'asc')->orderby('id', 'desc');
            }
            elseif ($input['sort'] == 'pd') {
                $data['goods'] = $data['goods']->orderby('price', 'desc')->orderby('id', 'desc');
            }
            elseif ($input['sort'] == 'c') {
                $data['goods'] = $data['goods']->orderby('count', 'asc')->orderby('id', 'desc');
            }
            elseif ($input['sort'] == 'cd') {
                $data['goods'] = $data['goods']->orderby('count', 'desc')->orderby('id', 'desc');
            }
            //未指定排序规则时按id倒序排序
            else
                $data['goods'] = $data['goods']->orderby('id', 'desc');
        }
        else
            $data['goods'] = $data['goods']->orderby('id', 'desc');

        $data['goods'] = $data['goods']->where('baned', 0)->paginate($paginate_limit);
        if($request->session()->has('user_id'))
            $data['user_id'] = $request->session()->get('user_id');
        else
            $data['user_id'] = NULL;
        if($request->session()->has('is_admin'))
            $data['is_admin'] = $request->session()->get('is_admin');
        else
            $data['is_admin'] = NULL;
        $data['page'] = 1;
        if(isset($input['page']) && $input['page'] != "") $data['page'] = $input['page'];
        $data['cat_id'] = $cat_id;
        $data['query'] = $query;
        $data['start_price'] = $start_price;
        $data['end_price'] = $end_price;
        $data['start_count'] = $start_count;
        $data['sort'] = $sort;
        //dd($data['cat_id'] == "");
        return view::make('good.goodList')->with($data);
    }

    /**
     * @function GoodController@getInfo
     * @input Request $request, $good_id
     * @return View
     * @description Get the information of a specify goods.
     */
    public function getInfo(Request $request, $good_id)
    {
        $data = [];
        $data['good'] = GoodInfo::with('tags')->where('id', $good_id)->first();
		$data['cats'] = GoodCat::orderby('cat_index', 'asc')->get();
        //return json_encode($data['good']);
		if($data['good']==NULL) return View::make('common.errorPage')->withErrors('商品ID错误！');
		if(($data['good']->baned) && ($data['good']->user_id != $request->session()->get('user_id') && !$request->session()->get('is_admin')))
			return View::make('common.errorPage')->withErrors('商品ID错误！');
        $data['user'] = User::where('id', $data['good']->user_id)->first();
		if($request->session()->has('user_id'))
		{
			$data['user_id'] = $request->session()->get('user_id');
			$data['inFvlst'] = FavList::where('user_id', $data['user_id'])->where('good_id', $good_id)->get();
		}
		else
		    $data['user_id'] = NULL;
		if($request->session()->has('is_admin'))
		    $data['is_admin'] = $request->session()->get('is_admin');
		else
		    $data['is_admin'] = NULL;
        return view::make('good.goodInfo')->with($data);
    }

    /**
     * @function GoodController@addGood
     * @input Request $request
     * @return Redirect
     * @description Add a new good.
     */
    public function showAddGood(Request $request)
    {
        $user_id = $request->session()->get('user_id');
        $user = User::find($user_id);
        $data = [];
        $data['cats'] = GoodCat::orderby('cat_index', 'asc')->get();
        $data['good'] = new GoodInfo;
        $data['add'] = true;
        //$data['tags'] = Tag::orderby('id', 'asc')->get();
        if(!$user || $user->baned)
            return view::make('good.goodInfoForm')->with($data)->withErrors('您的账号被封禁，无法出售商品，请联系系统管理员');
        return view::make('good.goodInfoForm')->with($data);
    }

    public function addGood(AddGoodRequest $request)
    {
        $user_id = $request->session()->get('user_id');
        $user = User::find($user_id);
        if(!$user || $user->baned)
            return Redirect::back()->withInput()->withErrors('您的账号被封禁，请联系系统管理员');
        $input = $request->all();
        $good = new GoodInfo;
        $good->good_name = $input['good_name'];
        $good->cat_id = $input['cat_id'];
        $good->description = clean($input['description']);
        $good->price = $input['price'];
        $good->type = $input['type'];
        $good->count = $input['count'];
        $good->user_id = $user_id;
        $good->baned = '0';
        $good->save();

        if(isset($input['new_tag_names'])) {
            $new_tag_names = $input['new_tag_names'];
            foreach ($new_tag_names as $tag_name) {
                $good_tag = new GoodTag;
                $tag = Tag::firstOrCreate(['tag_name' => $tag_name, 'good_cat_id' => $good->cat_id]);
                $good_tag->tag_id = $tag->id;
                $good_tag->good_id = $good->id;
                $good_tag->save();
            }
        }

        Storage::put(
            'good/titlepic/'.sha1($good->id),
            Image::make($request->file('goodTitlePic'))->crop(round($input['crop_width']),round($input['crop_height']),round($input['crop_x']),round($input['crop_y']))->resize(800, 450)->encode('data-url')
        );
        return Redirect::to('/good/'.$good->id);
    }

    /**
     * @function GoodController@editGood
     * @input Request $request, $good_id
     * @return Redirect
     * @description Edit a specify good.
     */
    public function showEditGood(Request $request, $good_id)
    {
        $data = [];
        $data['cats'] = GoodCat::orderby('cat_index', 'asc')->get();
        $data['good'] = GoodInfo::find($good_id);
        if($data['good'] == NULL) return View::make('common.errorPage')->withErrors('商品ID错误！');
        if($request->session()->get('user_id')!=$data['good']->user_id && $request->session()->get('is_admin')!=2)
            return Redirect::to('/good/'.$good_id);
        $data['add'] = false;
        /*$data['tags'] = Tag::orderby('id', 'asc')->get();
        $collection = GoodTag::where('good_id', $good_id)->pluck('tag_id');
        $data['this_good_tags'] = $collection->toArray();*/
        return view::make('good.goodInfoForm')->with($data);
    }

    public function editGood(EditGoodRequest $request, $good_id)
    {
        $input = $request->all();
        $good = GoodInfo::find($good_id);
        if($good == NULL) return View::make('common.errorPage')->withErrors('商品ID错误！');
        if($request->session()->get('user_id')!=$good->user_id && $request->session()->get('is_admin')!=2)
            return Redirect::to('/good/'.$good_id);
        $good->good_name=$input['good_name'];
        $good->cat_id=$input['cat_id'];
        $good->description= clean($input['description']);
        $good->price=$input['price'];
        $good->type=$input['type'];
        $good->count=$input['count'];
        $good->update();

        if(isset($input['new_tag_names'])) {
            $new_tag_names = $input['new_tag_names'];
            foreach ($new_tag_names as $tag_name) {
                $good_tag = new GoodTag;
                $tag = Tag::firstOrCreate(['tag_name' => $tag_name, 'good_cat_id' => $good->cat_id]);
                $good_tag->tag_id = $tag->id;
                $good_tag->good_id = $good->id;
                $good_tag->save();
            }
        }
        if(isset($input['del_tag_names'])) {
            $del_tag_names = $input['del_tag_names'];
            foreach($del_tag_names as $tag_name)
                GoodTag::where('good_id', $good_id)->where('tag_name', $tag_name)->delete();
        }


        if($request->hasFile('goodTitlePic'))
            Storage::put(
                'good/titlepic/'.sha1($good->id),
                Image::make($request->file('goodTitlePic'))->crop(round($input['crop_width']),round($input['crop_height']),round($input['crop_x']),round($input['crop_y']))->resize(800, 450)->encode('data-url')
            );
        return Redirect::to('/good/'.$good_id);
    }

    /**
     * @function GoodController@deleteGood
     * @input Request $request, $good_id
     * @return Redirect
     * @description Delete a specify good.
     */
    public function deleteGood(Request $request, $good_id)
    {
        $good = GoodInfo::find($good_id);
        if($request->session()->get('user_id') != $good->user_id && $request->session()->get('is_admin')!=2)
            return Redirect::to('/good/'.$good_id);
        Storage::delete('good/titlepic/'.sha1($good->id));
        $good->delete();
        GoodTag::where('good_id', $good_id)->delete();
        Favlist::where('good_id', $good_id)->delete();
        $trans = Transaction::where('good_id', $good_id)->where('status', '<' , 3)->get();
        foreach($trans as $tran)
        {
            $tran->status = 0;
            $buyer = User::find($tran->buyer_id);
            $msg = "【系统消息】您好！由于该商品被卖家删除，您的<a href='/user/trans'>订单（编号：".$tran->id."）</a>已被取消。非常抱歉。";
            $result = MessageController::sendMessageHandle(0, $tran->buyer_id, $msg);
            if($result['result'] && $buyer->wechat_open_id) {
                XMSHelper::sendSysMessage($buyer->wechat_open_id, $result['msg']);
                $result['msg']->wx_sent = true;
                $result['msg']->save();
            }
            $tran->update();
        }
        return Redirect::to('/good');
    }

	/*
	 * @function quickAccess
	 * @input $request (use query)
	 *
	 * @return Redirect or View
	 * @description Process the query and redirect to
	 *				certain good or list
	 */
	public function quickAccess(Request $request)
	{
		$data = [];
        $input = $request->all();
		$query = $input['query'];
		$data['goods'] = GoodInfo::where('good_name', 'like', "%$query%")->get();
		if($request->session()->has('user_id'))
		    $data['user_id'] = $request->session()->get('user_id');
		else
		    $data['user_id'] = NULL;
		if($request->session()->has('is_admin'))
	        $data['is_admin'] = $request->session()->get('is_admin');
		else
		    $data['is_admin'] = NULL;
        return view::make('good.goodList')->with($data);
	}

	public function addFavlist(Request $request, $good_id)
	{
		$fav = new FavList;
		$fav->user_id = $request->session()->get('user_id');
		$fav->good_id = $good_id;
		$fav->save();
		$good = GoodInfo::find($good_id);
		$good->fav_num++;
		$good->save();
		return json_encode(['msg' => 'success']);
	}

	public function delFavlist(Request $request, $good_id)
	{
		$user_id = $request->session()->get('user_id');
		FavList::where('user_id', $user_id)->where('good_id', $good_id)->delete();
        $good = GoodInfo::find($good_id);
        $good->fav_num--;
        $good->save();
		return json_encode(['msg' => 'success']);
	}

    public function getTitlePic(Request $request, $good_id, $width = 800, $height = 450)
    {
        if($width > 1920)
            $width = 1920;
        if($height > 1080)
            $height = 1080;
        if (!Storage::exists('good/titlepic/'.$good_id))
            $file = Storage::get('public/titlepic.jpg');
        else
            $file = Storage::get('good/titlepic/'.$good_id);
        $image = Image::make($file)->resize($width, $height);
        return $image->response('jpg');
    }

	public function banGood(Request $request, $good_id)
	{
		$good = GoodInfo::find($good_id);
		$good->baned = 1;
		$good->update();

		$admin_id = $request->session()->get('user_id');
		$user = User::find($good->user_id);
		$msg = "【系统消息】您好！您的<a href='/good/" . $good_id ."'>商品（编号：".$good_id."）</a>由于不符合有关规定已被管理员（ID：".$admin_id."）下架。请在此消息下询问具体细节。";

		$result = MessageController::sendMessageHandle($admin_id, $good->user_id, $msg);
		if($result['result'] && $user->wechat_open_id) {
            XMSHelper::sendSysMessage($user->wechat_open_id, $result['msg']);
            $result['msg']->wx_sent = true;
            $result['msg']->save();
        }

		$trans = Transaction::where('good_id', $good_id)->where('status', '<' , 3)->get();
		foreach($trans as $tran)
		{
			$tran->status = 0;
			$buyer = User::find($tran->buyer_id);
			$msg = "【系统消息】您好！由于该商品不符合有关规定被下架，您的<a href='/user/trans'>订单（编号：".$tran->id."）</a>已被取消。非常抱歉。";
			$result = MessageController::sendMessageHandle(0, $tran->buyer_id, $msg);
            if($result['result'] && $buyer->wechat_open_id) {
                XMSHelper::sendSysMessage($buyer->wechat_open_id, $result['msg']);
                $result['msg']->wx_sent = true;
                $result['msg']->save();
            }
			$tran->update();
		}

		 return Redirect::to('/good/'.$good_id);
	}

	public function queryTags(Request $request) {
	    $tag_name = $request->tag_name;
	    $tags = Tag::where('tag_name', 'like', $tag_name . "%")
            ->orderBy('tag_name', 'asc')
            ->get();
	    return json_encode($tags);
    }

    public function updateCat(Request $request, $good_id)
    {
        $input = $request->all();
        $good = GoodInfo::find($good_id);
        if ($good == NULL)
            return View::make('common.errorPage')->withErrors('商品ID错误！');
        $good->cat_id = $input['cat_id'];
        $good->update();
        return Redirect::to('/good/' . $good_id);
    }

    public function star(Request $request, $good_id)
    {
        $good = GoodInfo::find($good_id);
        if ($good == NULL)
            return View::make('common.errorPage')->withErrors('商品ID错误！');
        $good->stared = true;
        $good->save();
        return Redirect::to('/good/' . $good_id);
    }

    public function unstar(Request $request, $good_id)
    {
        $good = GoodInfo::find($good_id);
        if ($good == NULL)
            return View::make('common.errorPage')->withErrors('商品ID错误！');
        $good->stared = false;
        $good->save();
        return Redirect::to('/good/' . $good_id);
    }

}
