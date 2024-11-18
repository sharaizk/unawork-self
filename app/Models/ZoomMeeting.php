<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoomMeeting extends Model
{
    use HasFactory;
    protected $appends  = array(
        'project_name',
    );
    public function checkDateTime(){
        $m = $this;
        if (\Carbon\Carbon::parse($m->start_date)->addMinutes($m->duration)->gt(\Carbon\Carbon::now())) {
            return 1;
        }else{
            return 0;
        }
    }
    public function getProjectNameAttribute()
    {
        $project = Project::select('id', 'name')->where('id', $this->project_id)->first();

        return $project ? $project->name : null;
    }

    public function countMembers() {
        if (!empty($this->member_ids)) {
            $members_ids = explode(',', $this->member_ids);
            return count($members_ids);
        } else {
            return 0;
        }
    }

    public function getMembers(){
        if(!empty($this->member_ids)){
            $members_ids = explode(',',$this->member_ids);
            $members = User::select('id','name','avatar')->whereIn('id',$members_ids)->get();
            return $members;
        }else{
            return [];
        }
    }
    public function getClients(){
        if(!empty($this->client_id)){
            $client_ids = explode(',',$this->client_id);
            $clients = Client::select('id','name','avatar')->whereIn('id',$client_ids)->get();
            return $clients;
        }else{
            return [];
        }
    }

     public function projectName()
    {
        return $this->hasOne('App\Models\Project', 'id', 'project_id');
    }

    public function project()
    {
        return $this->hasOne('App\Models\Project', 'id', 'project_id');
    }


    public function getclientname(){
       return $this->hasOne('App\Models\Client', 'id', 'client_id');
    }

    public function getUserName(){
        $memberIds = explode(',', $this->member_ids);
        return User::whereIn('id', $memberIds)->get();
    }

    public function workspace()
    {
        return $this->hasOne('App\Models\Workspace', 'id', 'workspace_id');
    }
}
