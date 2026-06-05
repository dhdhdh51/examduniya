<?php
namespace PHPMailer\PHPMailer;

/**
 * SMTP helper class for PHPMailer
 * Handles low-level SMTP protocol communication
 */
class SMTP
{
    const VERSION = '6.9.0';
    const DEFAULT_SMTP_PORT = 25;
    const DEFAULT_SECURE_PORT = 465;
    const DEFAULT_TLS_PORT = 587;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;
    const CRLF = "\r\n";

    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';

    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $Timelimit = 300;
    public $Timeout = 30;

    protected $smtp_conn = null;
    protected $last_reply = '';
    protected $error = [];
    protected $helo_rply = '';

    /**
     * Connect to an SMTP server
     */
    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        if ($port === null) {
            $port = self::DEFAULT_SMTP_PORT;
        }

        $this->Timeout = $timeout;
        $errno = 0;
        $errstr = '';

        $socket_context = stream_context_create($options);
        $this->smtp_conn = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $socket_context
        );

        if (!is_resource($this->smtp_conn)) {
            $this->setError('Failed to connect to server', $errno, $errstr);
            return false;
        }

        stream_set_timeout($this->smtp_conn, $timeout);

        $announce = $this->getLines();
        if ($this->getResponseCode($announce) !== 220) {
            $this->setError('Invalid server greeting: ' . $announce);
            $this->close();
            return false;
        }

        return true;
    }

    /**
     * Send EHLO/HELO command
     */
    public function hello($host = '')
    {
        if (empty($host)) {
            $host = 'localhost';
        }

        if (!$this->sendCommand('EHLO', 'EHLO ' . $host, 250)) {
            if (!$this->sendCommand('HELO', 'HELO ' . $host, 250)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Initiate TLS (STARTTLS) negotiation
     */
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }

        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        $result = @stream_socket_enable_crypto($this->smtp_conn, true, $crypto_method);

        return (bool)$result;
    }

    /**
     * Authenticate via AUTH LOGIN
     */
    public function authenticate($username, $password, $authtype = 'LOGIN')
    {
        if (strtoupper($authtype) === 'LOGIN') {
            if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                return false;
            }
            if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                return false;
            }
            if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                return false;
            }
        } elseif (strtoupper($authtype) === 'PLAIN') {
            $auth_str = base64_encode("\0" . $username . "\0" . $password);
            if (!$this->sendCommand('AUTH', 'AUTH PLAIN ' . $auth_str, 235)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send MAIL FROM command
     */
    public function mail($from)
    {
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>', 250);
    }

    /**
     * Send RCPT TO command
     */
    public function recipient($address)
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $address . '>', [250, 251]);
    }

    /**
     * Send DATA command and message body
     */
    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        $field = '';
        $in_headers = true;

        foreach ($lines as $line) {
            if ($in_headers && $line === '') {
                $in_headers = false;
            }

            // Dot-stuffing
            if (!empty($line) && $line[0] === '.') {
                $line = '.' . $line;
            }

            // Line length check and wrapping
            if (strlen($line) > self::MAX_LINE_LENGTH) {
                $chunks = str_split($line, self::MAX_LINE_LENGTH - 1);
                foreach ($chunks as $chunk) {
                    $this->rawSend($chunk . self::CRLF);
                }
            } else {
                $this->rawSend($line . self::CRLF);
            }
        }

        // Send end of data marker
        if (!$this->sendCommand('DATA END', '.' , 250)) {
            return false;
        }

        return true;
    }

    /**
     * Send QUIT command
     */
    public function quit()
    {
        $this->sendCommand('QUIT', 'QUIT', 221);
        $this->close();
        return true;
    }

    /**
     * Close connection
     */
    public function close()
    {
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
        }
        $this->smtp_conn = null;
    }

    /**
     * Send RSET command
     */
    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    /**
     * Check if connection is active
     */
    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $status = stream_get_meta_data($this->smtp_conn);
            if ($status['eof']) {
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Send a command and check the response code
     */
    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!is_array($expect)) {
            $expect = [$expect];
        }

        $this->rawSend($commandstring . self::CRLF);

        $this->last_reply = $this->getLines();
        $code = $this->getResponseCode($this->last_reply);

        if (!in_array($code, $expect)) {
            $this->setError(
                $command . ' command failed',
                $code,
                $this->last_reply
            );
            return false;
        }

        return true;
    }

    /**
     * Raw send data to server
     */
    protected function rawSend($data)
    {
        if (!is_resource($this->smtp_conn)) {
            return false;
        }
        return fwrite($this->smtp_conn, $data);
    }

    /**
     * Read lines from server
     */
    protected function getLines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }

        $data = '';
        $endtime = time() + $this->Timelimit;

        stream_set_timeout($this->smtp_conn, $this->Timeout);

        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $line = @fgets($this->smtp_conn, self::MAX_REPLY_LENGTH);
            if ($line === false) {
                break;
            }
            $data .= $line;

            // If the 4th character is a space, this is the last line
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }

            // Also break on multi-line response end
            if (isset($line[3]) && $line[3] === '-') {
                continue;
            }

            if (time() > $endtime) {
                break;
            }
        }

        return $data;
    }

    /**
     * Get response code from server reply
     */
    protected function getResponseCode($reply)
    {
        if (empty($reply)) {
            return 0;
        }
        return (int)substr($reply, 0, 3);
    }

    /**
     * Set error details
     */
    protected function setError($message, $code = '', $detail = '')
    {
        $this->error = [
            'error' => $message,
            'smtp_code' => $code,
            'detail' => $detail
        ];
    }

    /**
     * Get last error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get last server reply
     */
    public function getLastReply()
    {
        return $this->last_reply;
    }
}
