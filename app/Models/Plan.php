<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'monthly_price',
        'annual_price',
        'storage_limit',
        'status',
        'enable_chatgpt',
        'trial_days',
        'max_workspaces',
        'max_users',
        'max_clients',
        'max_projects',
        'description',
        'image',
        'is_trial_disable',
        'allow_chat_project_management',
        'allow_files_project_management',
        'allow_expenses_project_management',
        'allow_bug_report_project_management',
        'allow_gannt_chart_project_management',
        'allow_workspace_project_view',
        'allow_pod_project_view',
        'allow_zoom_meeting_creation',
        'max_inner_circles',
        'is_custom',
    ];

    public function arrDuration()
    {
        return [
            'Unlimited' => 'Unlimited',
            'Month' => 'Per Month',
            'Year' => 'Per Year',
        ];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'access_plans');
    }

    public function getAvailablePlansForUser($userId)
    {
        return Plan::where('is_custom', false)
            ->orWhereHas('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();
    }

}
