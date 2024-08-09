<?php

namespace App\Enums\UserWorkspace;

enum Permission: string
{
    case USERS_INDEX = 'users_index';
    case USERS_EDIT = 'users_edit';
    
    case NEWS_INDEX = 'news_index';
    case NEWS_EDIT = 'news_edit';
    
    case PROJECTS_INDEX = 'projects_index';
    case PROJECTS_EDIT = 'projects_edit';
    
    case ZOOM_MEETING_INDEX = 'zoom_meeting_index';
    case ZOOM_MEETING_EDIT = 'zoom_meeting_edit';
}