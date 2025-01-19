<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name',
        'from',
        'slug',
        'created_by',
    ];

    public function template()
    {
        return $this->hasOne('App\Models\UserEmailTemplate', 'template_id', 'id')->where('user_id', '=', \Auth::user()->id);
    }

    private static $templateData = NULL;

    public static function emailTemplateData()
    {
        if(self::$templateData == null)
        {
            $emailTemplate     = EmailTemplate::first();
            self::$templateData = $emailTemplate;
        }
        return self::$templateData;
    }
}
