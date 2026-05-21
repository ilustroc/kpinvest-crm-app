<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkflowMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $title;
    public string $intro;
    public array $fields;
    public ?string $actionUrl;
    public ?string $actionText;
    public string $status;
    public string $module;

    public function __construct(
        string $subject,
        string $title,
        array $fields,
        ?string $actionUrl = null,
        ?string $actionText = null,
        string $intro = 'Se ha actualizado una solicitud en el CRM.',
        string $status = '',
        string $module = 'Workflow',
    ) {
        $this->subjectLine = $subject;
        $this->title = $title;
        $this->fields = $fields;
        $this->actionUrl = $actionUrl;
        $this->actionText = $actionText;
        $this->intro = $intro;
        $this->status = $status;
        $this->module = $module;
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('mail.workflow');
    }
}
