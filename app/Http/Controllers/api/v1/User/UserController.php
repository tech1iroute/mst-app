<?php
namespace App\Http\Controllers\api\v1\User;

use Illuminate\Http\Request; 
use App\Http\Controllers\Controller; 
use App\User; 
use App\Post; 
use App\Vendor; 
use App\MainCategory; 
use App\SubCategory; 
use App\CategoryActivity; 
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Hash;
use Validator;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Socialite;
//use Illuminate\Support\Facades\Storage;
//use Illuminate\Support\Facades\File;
use Image;

class UserController extends Controller 
{
use AuthenticatesUsers;
public $successStatus = 200;
protected $maxAttempts  = 3; // Amount of bad attempts user can make
protected $decayMinutes = 1; // Time for which user is going to be blocked in minutes
protected $posts;

public function __construct()
{
    $this->posts =new Post();
}
//use AuthenticatesUsers;

    /** 
     * login api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function login(Request $request){ 

        $validator = Validator::make($request->all(), [ 
            //'user_mobile' => 'required|min:11|numeric',
            'user_mobile' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) { 
            return response()->json(['response'=>$validator->errors()], 401);  
        }

        if(Auth::attempt(['user_mobile' => request('user_mobile'), 'password' => request('password')])){ 
            $user = Auth::user(); 

            $token =  $user->createToken('mst-app')-> accessToken; 

            $response_array['status']='success';
            $response_array['response_message']='User successfully logged in';
            $response_array['data']=array('token'=>$token,'user_details'=>$user);
            return response()->json(['response' => $response_array], $this-> successStatus); 
        } 
        else{ 

            if ($this->hasTooManyLoginAttempts($request)) {
                  $this->fireLockoutEvent($request);
                  return response()->json(['response'=>'Too many login attempts. Please try again in 1 minute.'], 401);
            }

            $this->incrementLoginAttempts($request);
            if($request->wantsJson()) { // based on HTTP headers as above
                return response()->json(__('errors.not_found'), \Illuminate\Http\Response::HTTP_NOT_FOUND);
            }

            $response_array['status']='fail';
            $response_array['response_message']='There is a problem in logged in';
            return response()->json(['response'=>$response_array], 401); 
        } 
    }
    /** 
     * Register api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function register(Request $request) 
    { 
        $validator = Validator::make($request->all(), [ 
          'user_fname' => ['required', 'string', 'max:255'], 
          'user_lname' => ['required', 'string', 'max:255'],
          'user_email' => ['required', 'string', 'email', 'max:255'],
          'password' => ['required', 'string', 'min:6'], 
          'user_mobile' => 'required|min:10|numeric|unique:tbl_user',
          'dob' => 'required',
          'user_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:1024',
        ]);
        if ($validator->fails()) { 
          return response()->json(['response'=>$validator->errors()], 401);            
        }
        $input = $request->all(); 
        $user_image = $input['user_image'];
        $imagename = time().'.'.$user_image->getClientOriginalExtension(); 
        $destinationPath = public_path('/thumbnail_images');
        $thumb_img = Image::make($user_image->getRealPath())->resize(100, 100);
        $thumb_img->save($destinationPath.'/'.$imagename,80);
                    
        $destinationPath = public_path('/normal_images');
        $user_image->move($destinationPath, $imagename);
        $input['user_image'] = $imagename;

        $input['password'] = bcrypt($input['password']); 
        $input['user_code'] = $this->generateRandomString(6);// it should be dynamic and unique 
        $user = User::create($input); 
        $token =  $user->createToken('mst-app')-> accessToken; 
        $response_array['status']='success';
        $response_array['response_message']='User successfully Registered.';
        $response_array['data']=array('token'=>$token,'user_details'=>$user);
        return response()->json(['response' => $response_array], $this-> successStatus);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [ 
            'user_fname' => ['required', 'string', 'max:255'], 
            'user_lname' => ['required', 'string', 'max:255'],
            'dob' => 'required',
        ]);
        if ($validator->fails()) { 
          return response()->json(['response'=>$validator->errors()], 401);            
        }
        $user = User::find($id);
        //print_r($user); die;
        $input = $request->all();
        $user->user_fname = $input['user_fname'];
        $user->user_lname = $input['user_lname'];
        $user->dob = $input['dob'];
        $user->save();
        //$success[] =  $user;
        //return response()->json(['success'=>$success], $this-> successStatus); 
        $response_array['status']='success';
        $response_array['response_message']='Successfully User details updated.';
        $response_array['data']=array('user_details'=>$user);
        return response()->json(['response' => $response_array], $this-> successStatus);
    }

    /** 
     * details api 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function details() 
    { 
        $user = Auth::user(); 
        //return response()->json(['success' => $user], $this-> successStatus); 
        $response_array['status']='success';
        $response_array['data']=array('user_details'=>$user);
        return response()->json(['response' => $response_array], $this-> successStatus); 
    } 

    public function user_details($id) 
    {
        $user = User::find($id);
        if (is_null($user)) {
            return response()->json(['response'=>'User not found.'], 401); 
        }
        //return response()->json(['success' => $user], $this-> successStatus);
        $response_array['status']='success';
        $response_array['data']=array('user_details'=>$user);
        return response()->json(['response' => $response_array], $this-> successStatus); 
    }

    public function logout(Request $request)
    {
        //$success['success'] =  true;
       // $success['message'] =  'Successfully logged out';
        $request->User()->token()->revoke();
        //return response()->json(['result'=>$success], $this-> successStatus); 

        $response_array['status']='success';
        $response_array['response_message']='Successfully logged out';
        //$response_array['data']=array('token'=>$token,'user_details'=>$user);
        return response()->json(['response' => $response_array], $this-> successStatus); 
    }


    /**
     * Redirect the user to the facebook authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToProvider()
    {
        return  Socialite::driver('facebook')->redirect();
    }

    /**
     * Obtain the user information from facebook.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleProviderCallback()
    {
        $facebookUser = Socialite::driver('facebook')->stateless()->user();
        $user = User::where('facebook_id',$facebookUser->facebook_id())->first();
        if(!$user){
            //add users to database
            $user = User::create([
              'user_fname'     => $facebookUser->name(),
              'user_email'    => $facebookUser->email(),
              'facebook_id' => $facebookUser->id(),
              //'provider_id' => $user->id,
            ]);
        }
        $success[] =  $user;
        return response()->json(['success'=>$success], $this-> successStatus); 
    }

    public  function generateRandomString($length = 20) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return 'GINI'.$randomString;
    }

    public function getPosts(){
      $user = Auth::user(); 
      //print_r($user);
      $user_posts = $user->posts ->All();
      //print_r($user_posts);
      if($user_posts){
      $response_array['status']='success';
      $response_array['data']=array('post_details'=>$user_posts);
      return response()->json(['response' => $response_array], $this-> successStatus);

      } 
      else{ 
          $response_array['status']='fail';
          $response_array['response_message']='There is no product to show of logged in user.';
          return response()->json(['response'=>$response_array], 401); 
      } 

    }


    public function getVendor(){
      $user = Auth::user(); 
      //print_r($user);
      $user_vendor = $user->vendor ->All();
      //print_r($user_vendor);
      if($user_vendor){
      $response_array['status']='success';
      $response_array['data']=array('vendor_details'=>$user_vendor);
      return response()->json(['response' => $response_array], $this-> successStatus);

      } 
      else{ 
          $response_array['status']='fail';
          $response_array['response_message']='This user not belongs to any vendor.';
          return response()->json(['response'=>$response_array], 401); 
      } 

    }



    protected function hasTooManyLoginAttempts(Request $request) {

        return $this->limiter()->tooManyAttempts($this->throttleKey($request), $this->maxAttempts, $this->decayMinutes);

    }

}