<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Task;
use \Firebase\JWT\JWT;
use Auth;
use App\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Mail;

class TaskController extends Controller
{

    public function __construct()
    {
        //
    }

    public function add_task(Request $request){

        date_default_timezone_set('Asia/Kolkata');
        $time = date('Y-m-d H:i:s');

        $users = $request->users;
        // if($users->role != 'admin'){
        //     return response()->json(["You are not the admin"],403);
        // }

        $this->validate($request, [
            'title' => 'required|string|max:50',
            'task' => 'required|string|max:200',
            'deadline' => "required|date|after:$time", // validation
            //'assigner' => 'required',  // 
            'assigned_to' => 'required|exists:users,id', // 
        ]);

        $title = $request->title;
        $task = $request->task;
        $deadline = $request->deadline;
        $assigner = $users->name;
        $assigner_id  = $users->id;
        $assigned_to = $request->assigned_to;

        $users = USER::where('id' , $assigned_to)->first();
        $email = $users->email;

        $tasks = new Task;
        $tasks->title = $title;
        $tasks->task = $task;
        $tasks->deadline = $deadline;
        $tasks->assigner = $assigner;
        $tasks->assigned_to = $assigned_to;
        $tasks->assigner_id = $assigner_id;
        $tasks->save();

        Mail::raw("Task Assigned \n Title : $title \n Description : $task \n Deadline : $deadline"
        , function ($message) use($email) {
            $message->to($email)
              ->subject('New Task');
          });
          if (Mail::failures()) {
            return response()->json(["Task mail can't be sent "],201);
          } 
        

        return response()->json(['successfull']);

    }

    public function get_task(Request $request){

        $users = $request->users;
        // if($users->role != 'admin'){
        //     return response()->json(["You are not the admin"],403);
        // }

        $this->validate($request, [
            'id' => 'required|exists:tasks',   // this is id of task
        ]);

        $id = $request->id;
        $display = Task::where('id' , $id)->first();

        if($display->assigner_id === $users->id || $users->role ==='admin'){
            return response()->json($display ,200);
        }
        else{
            return response()->json(["Unauthorised"],403);
        }
    }

    public function view_task(Request $request){

        $this->validate($request, [
            'id' => 'required|exists:users',  // this is id of user
        ]);
        $users = $request->users;

        $id = $request->id;
        $check = $request->check;
        $progress = $request->progress;
        $que = $request->que;

        if($users->role != 'admin'){
            if($id != $users->id){
                return response()->json(['unauthorised'],403);
            }
        }

        // if($users->id != $id){
        //     return response()->json(["Invalid Request"],);
        // }

        if($progress === 'all'){
            $progress = '';  //when run if variable is empty
        }

        date_default_timezone_set('Asia/Kolkata');
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');

        // $display = Task::where('progress' , $progress)
        //     ->where('assigned_to' , $id)->get();
        
        if($check == 'true'){ // for viewing particular type of task
             $display = Task::where('deadline', 'like' , '%'. $date .'%')
                ->where('assigned_to' , $id)
                ->where('progress' ,'like', '%'. $progress .'%')
                -> where( function($q) use($que){
                    $q->where('title' ,'like', '%'. $que .'%')
                    ->orWhere('task' ,'like', '%'. $que .'%');
                })
                ->orderBy('id', 'DESC')
                 ->paginate(2);
        }
        else{
            $display = Task::where('assigned_to' , $id)
                ->where('progress' ,'like', '%'. $progress .'%')
                -> where( function($q) use($que){
                    $q->where('title' ,'like', '%'. $que .'%')
                    ->orWhere('task' ,'like', '%'. $que .'%');
                })
                ->orderBy('id', 'DESC')
                ->paginate(2);
        }

        //echo "$display.data";
        $len = sizeof($display);
        for($i = 0 ; $i < $len ; $i++){   //for overdue
           if($display[$i]->deadline < $time && ($display[$i]->progress ==='pending' || $display[$i]->progress === 'in_progress' )){
               $display[$i]->overdue = true;
           }
           else{
            $display[$i]->overdue = false;
           }
        }
        return response()->json($display,200);
    }


    public function complete_task(Request $request){

        $this->validate($request, [
            'id' => 'required|exists:users',  // id of task
        ]);

        $id = $request->id;
        $display =Task::where('id',$id) ->first();
        date_default_timezone_set('Asia/Kolkata');
        $time = date('Y-m-d H:i:s');
        // $date = date('Y-m-d H:i:s',strtotime('+5 hour +30 minutes',strtotime($date)));
        if($time <= $display->deadline){
            $display->progress = 'completed_on_time';
        }
        else{
            $display->progress = 'completed_late';
        }
        $display->status = true;
        $display ->save();

        return response()->json(["done successfully"]);

    }

    public function active_task(Request $request){    // of no use now
        $this->validate($request, [
            'id' => 'required|exists:tasks',   // id of task
        ]);
        $id = $request->id;
        //echo "id : $id";
        $display =Task::where('id',$id) ->first();

        if($display->progress === 'pending'){
            $display->progress = 'in_progress';
        }
        elseif($display->progress === 'in_progress'){
            $display->progress = 'pending';
        }
        $display->save();

        return response()->json('successfull');

    }

    public function submit_progress(Request $request){
        $this->validate($request, [
            'id' => 'required|exists:tasks',   // id of task
            'progress' => 'required'  //check value from enum
        ]);

        date_default_timezone_set('Asia/Kolkata');
        $time = date('Y-m-d H:i:s');

        /// check for particular user

        $id = $request->id;
        $progress = $request->progress;

        $display =Task::where('id',$id) ->first();

        if($progress === 'pending' || $progress === 'in_progress'){
            $display->progress = $progress;
            $display->save();
            return response()->json(['successfull'], 200);
        }
        elseif($progress === 'completed'){
            if($time <= $display->deadline){
                $display->progress = 'completed_on_time';
            }
            else{
                $display->progress = 'completed_late';
            }
            $display->save();
            return response()->json(['successfull'],200);
        }
        else{
            return response()->json(['Invalid Request'],422);
        }

        
    }

    public function delete_task(Request $request){

        $users = $request->users;
        // if($users->role != 'admin'){
        //     return response()->json(["You are not the admin"],403);
        // }

        $this->validate($request, [
            'id' => 'required|exists:tasks',  // id of task
        ]);
        $id = $request->id;

        if(!($users->id === $tasks->assigner_id || $users->role === 'admin')){
            return response()->json(["Unauthorised"],403);
        } 

        $display =Task::where('id',$id) ->first();
        $display->delete();

        return response()->json(["deleted successfully"],202);

    }
    
    public function update_task(Request $request){

        date_default_timezone_set('Asia/Kolkata');
        $time = date('Y-m-d H:i:s');

        $this->validate($request, [
            'id' => 'required|exists:tasks',  // id of task
            'title' => 'required|string|max:50',
            'task' => 'required|string|max:200',
            'deadline' => 'required|date', // validation for previous date
        ]);

        $users = $request->users;
        // if($users->role !== 'admin' ){
        //     return response()->json(["Unauthorised"],403);
        // }

        $id = $request->id;
        $title = $request->title;
        $task = $request->task;
        $deadline = $request->deadline;

        $tasks = Task::where('id' , $id)->first();

        if(!($users->id === $tasks->assigner_id || $users->role === 'admin')){
            return response()->json(["Unauthorised"],403);
        } 
        $assigned_to = $tasks->assigned_to;
        $tasks->title = $title;
        $tasks->task = $task;
        $tasks->deadline = $deadline;
        $tasks->save();

        $users = USER::where('id' , $assigned_to)->first();
        $email = $users->email;

        Mail::raw("Task Updated \n Title : $title \n Description : $task \n Deadline : $deadline"
        , function ($message) use($email) {
            $message->to($email)
              ->subject('Task Updated');
          });
          if (Mail::failures()) {
            return response()->json(["mail can't be sent "]);
          } 
        
        return response()->json(['successfull'],200);

    }
    
    public function piechart(Request $request){
        $this->validate($request, [
            'id' => 'required'  // id of user
        ]);
        $id = $request->id;
        $users = $request->users;

        if($users->role != 'admin'){
            if($id != $users->id){
                return response()->json(['unauthorised'],403);
            }
        }

        date_default_timezone_set('Asia/Kolkata');
        $time = date('Y-m-d H:i:s');

        if($id === 0){   
            $display = Task::all();
        }
        else{
            $display = Task::where('assigned_to' , $id)->get();
        }

        // $values = ['pending' => 0 , 'in_progress' => 0 , 'overdue' => 0 , 'completed_late' => 0 , 'completed_on_time' => 0 ];

        $pending = 0;
        $in_progress = 0 ;
        $overdue = 0;
        $completed_late = 0;
        $completed_on_time = 0;

        $len = sizeof($display);

        for($i = 0 ; $i < $len ; $i++){
            if($display[$i]->deadline < $time && ($display[$i]->progress ==='pending' || $display[$i]->progress === 'in_progress' )){
                $overdue++;
            }
            else{
                switch($display[$i]->progress){
                case 'pending':
                    $pending++;
                break;
                case 'in_progress' :
                    $in_progress++;
                break;
                case 'completed_on_time' :
                    $completed_on_time++;
                break;
                case 'completed_late' :
                    $completed_late++;
                break;
            }}
            
        }

        //echo "$pending";
        return response()
        ->json(['pending' => $pending , 'overdue' => $overdue , 'in_progress' => $in_progress , 'completed_on_time' => $completed_on_time , 'completed_late' => $completed_late],200);
    }

    public function all_task(Request $request){


        $users = $request->users;
        // if($users->role != 'admin'){
        //     return response()->json(["You are not the admin"],403);
        // }

        $id = $users->id;
        
        $check = $request->check;
        $progress = $request->progress;
        $que = $request->que;

        if($progress === 'all'){
            $progress = '';
        }

        date_default_timezone_set('Asia/Kolkata');
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');

        if($users->role === 'admin'){
            if($check == 'true'){ // check for viewing todays of task
                $display = Task::where('deadline', 'like' , '%'. $date .'%')
                   ->where('progress' ,'like', '%'. $progress .'%')
                //    ->when($users->role !== 'admin' , function($q , $id) {
                //        $q->where('assigner_id' , $id);
                //    })
                   -> where( function($q) use($que){
                       $q->where('title' ,'like', '%'. $que .'%')
                       ->orWhere('task' ,'like', '%'. $que .'%');
                   })
                   ->orderBy('id', 'DESC')
                    ->paginate(4);
           }
           else{
               $display = Task::where('progress' ,'like', '%'. $progress .'%')
                   -> where( function($q) use($que){
                       $q->where('title' ,'like', '%'. $que .'%')
                       ->orWhere('task' ,'like', '%'. $que .'%');
                   })
                   ->orderBy('id', 'DESC')
                   ->paginate(4);
           }
        }

        else{
            if($check == 'true'){ // check for viewing todays of task
                $display = Task::where('deadline', 'like' , '%'. $date .'%')
                   ->where('progress' ,'like', '%'. $progress .'%')
                   ->where('assigner_id' , $id)
                   -> where( function($q) use($que){
                       $q->where('title' ,'like', '%'. $que .'%')
                       ->orWhere('task' ,'like', '%'. $que .'%');
                   })
                   ->orderBy('id', 'DESC')
                    ->paginate(4);
           }
           else{
               $display = Task::where('progress' ,'like', '%'. $progress .'%')
                   ->where('assigner_id' , $id)
                   -> where( function($q) use($que){
                       $q->where('title' ,'like', '%'. $que .'%')
                       ->orWhere('task' ,'like', '%'. $que .'%');
                   })
                   ->orderBy('id', 'DESC')
                   ->paginate(4);
           }
        }

        $len = sizeof($display);
        for($i = 0 ; $i < $len ; $i++){   //for over_due

            $id = $display[$i]->assigned_to;
            $users = USER::where('id' , $id)->first();
            $name = $users->name;
            $display[$i]->name = $name;

           if($display[$i]->deadline < $time && ($display[$i]->progress ==='pending' || $display[$i]->progress === 'in_progress' )){
               $display[$i]->overdue = true;
           }
           else{
            $display[$i]->overdue = false;
           }
        }
        // for($i = 0 ; $i < $len ; $i++){   //for over_due
        //     $temp = $display[$i]->id;
        //     $edit = Task::where('id',$temp) ->first();
        //     if(($edit->progress == 'pending' || $edit->progress == 'in_progress') && $time > $edit->deadline ){
        //         $edit->progress = 'overdue';
        //         $display[$i]->progress = 'overdue';
        //         $edit->save();
        //     }
        // }

        return response()->json($display,200);
    }


}
