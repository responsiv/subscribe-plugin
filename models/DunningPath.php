<?php namespace Responsiv\Subscribe\Models;

use Str;
use Model;
use System\Models\MailTemplate;

/**
 * Dunning Path Model
 */
class DunningPath extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'responsiv_subscribe_dunning_paths';

    public $rules = [
        'failed_attempts' => 'required|numeric',
    ];

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'admin_group' => 'Backend\Models\UserGroup',
        'status' => ['Responsiv\Subscribe\Models\Status', 'conditions' => "code in ('cancelled', 'pastdue')"],
    ];

    //
    // Attributes
    //

    public function getDescriptionAttribute()
    {
        $message = sprintf(
            'After %s failed renewal %s, ',
            $this->failed_attempts,
            Str::plural('attempt', $this->failed_attempts)
        );

        $actions = [];

        if ($this->status) {
            $actions[] = sprintf('change the subscription status to "%s"', $this->status->name);
        }

        if ($this->user_template) {
            $actions[] = sprintf('send the "%s" notification to the user', $this->user_template);
        }

        if ($this->admin_template) {
            $adminMessage = sprintf('send the "%s" notification to administrators ', $this->admin_template);

            if ($this->admin_group) {
                $adminMessage .= sprintf('in group "%s"', $this->admin_group->name);
            }

            $actions[] = trim($adminMessage);
        }


        if (empty($actions)) {
            $actions[] = 'do nothing';
        }

        $message .= implode(' and ', $actions) . '.';

        return trim($message);
    }

    //
    // Options
    //

    public function getUserTemplateOptions()
    {
        return $this->getMailTemplates();
    }

    public function getAdminTemplateOptions()
    {
        return $this->getMailTemplates();
    }

    protected function getMailTemplates()
    {
        $codes = array_keys(MailTemplate::listAllTemplates());
        $result = array_combine($codes, $codes);
        return $result;
    }
}
