<?php

namespace Baka\Mail;

use Exception;

/**
 * Class Message
 *
 * @package Phalcon\Mailer
 */
class Message extends \Phalcon\Mailer\Message
{
    protected $queueName = 'email_queue';
    protected $viewPath = null;
    protected $params = null;
    protected $viewsDirLocal = null;
    protected $smtp = null;
    protected $auth = false;

    /**
     * Set the body of this message, either as a string, or as an instance of
     * {@link \Swift_OutputByteStream}.
     *
     * @param mixed $content
     * @param string $contentType optional
     * @param string $charset     optional
     *
     * @return $this
     *
     * @see \Swift_Message::setBody()
     */
    public function content($content, $contentType = self::CONTENT_TYPE_HTML, $charset = null)
    {
        $this->getMessage()->setBody($content, $contentType, $charset);

        return $this;
    }

    /**
     * Send the given Message like it would be sent in a mail client.
     *
     * All recipients (with the exception of Bcc) will be able to see the other
     * recipients this message was sent to.
     *
     * Recipient/sender data will be retrieved from the Message object.
     *
     * The return value is the number of recipients who were accepted for
     * delivery.
     *
     * Events:
     * - mailer:beforeSend
     * - mailer:afterSend
     *
     * @return int
     *
     * @see \Swift_Mailer::send()
     */
    public function send()
    {
        $eventManager = $this->getManager()->getEventsManager();
        if ($eventManager) {
            $result = $eventManager->fire('mailer:beforeSend', $this);
        } else {
            $result = true;
        }

        if ($result === false) {
            return false;
        }

        $this->failedRecipients = [];

        //send to queue
        $queue = $this->getManager()->getQueue();
        //$queueName = $this->

        if ($this->auth) {
            $queue->putInTube($this->queueName, [
                'message' => $this->getMessage(),
                'auth' => $this->smtp,
            ]);
        } else {
            $queue->putInTube($this->queueName, $this->getMessage());
        }

        /* $count = $this->getManager()->getSwift()->send($this->getMessage(), $this->failedRecipients);
    if ($eventManager) {
    $eventManager->fire('mailer:afterSend', $this, [$count, $this->failedRecipients]);
    }
    return $count;*/
    }

    /**
     * Send message instantly, without a queue
     *
     * @return void
     */
    public function sendNow()
    {
        $config = $this->getManager()->getDI()->getConfig();
        $message = $this->getMessage();

        $username = $config->email->username;
        $password = $config->email->password;
        $host = $config->email->host;
        $port = $config->email->port;
        
        $transport = \Swift_SmtpTransport::newInstance($host, $port);
        
        $transport->setUsername($username);
        $transport->setPassword($password);
        
        $swift = \Swift_Mailer::newInstance($transport);
        
        $failures = [];
        
        $swift->send($message, $failures);
    }

    /**
     * Overwrite the baka SMTP connection for this current email
     *
     * @param  array  $smtp
     * @return this
     */
    public function smtp(array $params)
    {
        //validate the user params
        if (!array_key_exists('username', $params)) {
            throw new Exception("We need a username");
        }

        if (!array_key_exists('password', $params)) {
            throw new Exception("We need a password");
        }

        $this->smtp = $params;
        $this->auth = true;

        return $this;
    }

    /**
     * Set the queue name if the user wants to shange it
     *
     * @param string $queuName
     *
     * @return $this
     */
    public function queue($queue)
    {
        $this->queueName = $queue;
        return $this;
    }

    /**
     * Set variables to views
     *
     * @param string $params
     *
     * @return $this
     */
    public function params($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * The local path to the folder viewsDir only this message. (OPTIONAL)
     *
     * @param string $dir
     *
     * @return $this
     */
    public function viewDir($dir)
    {
        $this->viewsDirLocal = $dir;
        return $this;
    }

    /**
     * view relative to the folder viewsDir (REQUIRED)
     *
     * @param string $template
     *
     * @return $this
     */
    public function template($template = 'email.volt')
    {
        $this->viewPath = $template;

        //if we have params thats means we are using a template
        if (is_array($this->params)) {
            $content = $this->getManager()->setRenderView($this->viewPath, $this->params);
        }

        $this->getMessage()->setBody($content, self::CONTENT_TYPE_HTML);

        return $this;
    }
}
