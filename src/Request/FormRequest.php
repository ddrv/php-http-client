<?php

namespace Ddrv\Http\Client\Request;

class FormRequest extends Request
{
    protected $method = 'POST';

    protected $fields = array();

    protected $files = array();

    protected $headers = array(
        'Content-Type' => array(
            'application/x-www-form-urlencoded'
        ),
    );

    public function field($field, $value) {
        $this->fields[$field] = $value;
        return $this;
    }

    public function file($field, $file) {
        if (!is_readable($file)) return $this;
        $info = pathinfo($file);
        $this->files[$field] = [
            'name' => $info['basename'],
            'file' => $file,
            'mime' => mime_content_type($file),
        ];
        return $this;
    }

    public function fileFromString($field, $name, $string, $mime = 'text/plain') {
        $this->files[$field] = [
            'name' => $name,
            'content' => $string,
            'mime' => $mime,
        ];
        return $this;
    }

    public function send()
    {
        if ($this->files) {
            $boundary = md5(rand(10,1000));
            $eol = "\r\n";
            $this->body = $eol;
            foreach ($this->fields as $field=>$value) {
                $this->body .= '--'.$boundary.$eol;
                $this->body .='Content-Disposition: form-data; name="'.$field.'"'.$eol.$eol;
                $this->body .= $value.$eol;
            }
            $this->headers['Content-Type'] = [
                'multipart/form-data; boundary='.$boundary,
            ];
            foreach ($this->files as $field => $file) {
                if (!key_exists('file', $file) && !key_exists('content', $file)) {
                    continue;
                }
                $this->body .= '--'.$boundary.$eol;
                $this->body .='Content-Disposition: form-data; name="'.$field.'"; filename="'.$file['name'].'"'.$eol;
                $this->body .= 'Content-Type: '.$file['mime'].$eol.$eol;
                if (key_exists('file', $file)) {
                    $this->body .= file_get_contents($file['file']);
                } elseif (key_exists('content', $file)) {
                    $this->body .= $file['content'];
                }
                $this->body .= $eol;
            }
            $this->body .= '--'.$boundary.'--'.$eol;
        } else {
            $this->body = http_build_query($this->fields);
        }
        $this->headers['Content-Length'] = [
            strlen($this->body),
        ];
        return parent::send();
    }


}