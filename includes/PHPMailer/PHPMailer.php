<?php
namespace PHPMailer\PHPMailer;

/**
 * PHPMailer 6.x compatible SMTP mailer
 * Full SMTP implementation using fsockopen/stream_socket_client
 */
class PHPMailer
{
    const VERSION = '6.9.0';
    const CHARSET_UTF8 = 'UTF-8';
    const CHARSET_ASCII = 'us-ascii';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    const CRLF = "\r\n";

    // Sending options
    public $Mailer = 'mail';
    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPAuth = false;
    public $SMTPSecure = '';
    public $Username = '';
    public $Password = '';
    public $Timeout = 30;
    public $SMTPOptions = [];
    public $SMTPDebug = 0;
    public $AuthType = 'LOGIN';

    // Message properties
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $CharSet = self::CHARSET_UTF8;
    public $ContentType = self::CONTENT_TYPE_TEXT_PLAIN;
    public $Encoding = 'quoted-printable';
    public $Helo = '';
    public $WordWrap = 0;
    public $Priority;

    // Misc
    public $XMailer = '';
    public $DKIM_domain = '';
    public $DKIM_private = '';
    public $DKIM_selector = '';
    public $DKIM_passphrase = '';
    public $DKIM_identity = '';
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];

    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $mailHeader = '';
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected $exceptions = false;
    protected $uniqueid = '';
    public $ErrorInfo = '';

    const CONTENT_TYPE_TEXT_PLAIN = 'text/plain';

    public function __construct($exceptions = false)
    {
        $this->exceptions = (bool)$exceptions;
    }

    /**
     * Set mailer to use SMTP
     */
    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    /**
     * Set sender address and name
     */
    public function setFrom($address, $name = '', $auto = true)
    {
        $address = $this->punyencodeAddress(trim($address));
        $name = trim(preg_replace('/[\r\n]+/', '', $name));

        if (!$this->validateAddress($address)) {
            $this->setError('Invalid address: (setFrom) ' . $address);
            return false;
        }

        $this->From = $address;
        $this->FromName = $name;

        if ($auto) {
            if (empty($this->Sender)) {
                $this->Sender = $address;
            }
        }

        return true;
    }

    /**
     * Add a recipient address
     */
    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    /**
     * Add a CC recipient
     */
    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    /**
     * Add a BCC recipient
     */
    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    /**
     * Add a Reply-To address
     */
    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    /**
     * Set message to HTML
     */
    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = self::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->ContentType = self::CONTENT_TYPE_PLAINTEXT;
        }
    }

    /**
     * Set mailer to use PHP mail() function
     */
    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    /**
     * Clear all To recipients
     */
    public function clearAddresses()
    {
        foreach ($this->to as $to) {
            unset($this->all_recipients[strtolower($to[0])]);
        }
        $this->to = [];
    }

    /**
     * Clear all CC recipients
     */
    public function clearCCs()
    {
        foreach ($this->cc as $cc) {
            unset($this->all_recipients[strtolower($cc[0])]);
        }
        $this->cc = [];
    }

    /**
     * Clear all attachments
     */
    public function clearAttachments()
    {
        $this->attachment = [];
    }

    /**
     * Clear all custom headers
     */
    public function clearCustomHeaders()
    {
        $this->CustomHeader = [];
    }

    /**
     * Add a custom header
     */
    public function addCustomHeader($name, $value = null)
    {
        if ($value === null) {
            if (strpos($name, ':') === false) {
                return false;
            }
            list($name, $value) = explode(':', $name, 2);
        }
        $this->CustomHeader[] = [trim($name), trim($value)];
        return true;
    }

    /**
     * Send the message
     * @throws Exception
     */
    public function send()
    {
        if (empty($this->to) && empty($this->cc) && empty($this->bcc)) {
            $this->setError('You must provide at least one recipient email address.');
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }

        $this->Sender = empty($this->Sender) ? $this->From : $this->Sender;

        $this->uniqueid = $this->generateId();
        $this->lastMessageID = sprintf(
            '<%s@%s>',
            $this->uniqueid,
            $this->serverHostname()
        );

        $this->MIMEHeader = $this->createHeader();
        $this->MIMEBody = $this->createBody();

        return $this->sendMessageViaSMTP();
    }

    /**
     * Get message headers
     */
    public function createHeader()
    {
        $result = '';
        $result .= $this->headerLine('Date', self::rfcDate());
        $result .= $this->addrAppend('From', [[$this->From, $this->FromName]]);

        if (!empty($this->ReplyTo)) {
            $result .= $this->addrAppend('Reply-To', $this->ReplyTo);
        }

        if (!empty($this->to)) {
            $result .= $this->addrAppend('To', $this->to);
        }
        if (!empty($this->cc)) {
            $result .= $this->addrAppend('Cc', $this->cc);
        }

        $result .= $this->headerLine('Subject', $this->encodeHeader($this->secureHeader($this->Subject)));
        $result .= $this->headerLine('Message-ID', $this->lastMessageID);
        $result .= $this->headerLine('X-Mailer', 'PHPMailer ' . self::VERSION);
        $result .= $this->headerLine('MIME-Version', '1.0');

        if ($this->ContentType === self::CONTENT_TYPE_TEXT_HTML && !empty($this->AltBody)) {
            $boundary = 'b1_' . $this->uniqueid;
            $result .= $this->headerLine('Content-Type', 'multipart/alternative; boundary="' . $boundary . '"');
        } else {
            $result .= $this->headerLine('Content-Type', $this->ContentType . '; charset=' . $this->CharSet);
            $result .= $this->headerLine('Content-Transfer-Encoding', $this->Encoding);
        }

        foreach ($this->CustomHeader as $header) {
            $result .= $this->headerLine(
                trim($header[0]),
                $this->encodeHeader(trim($header[1]))
            );
        }

        return $result;
    }

    /**
     * Create message body
     */
    public function createBody()
    {
        $body = '';

        if ($this->ContentType === self::CONTENT_TYPE_TEXT_HTML && !empty($this->AltBody)) {
            $boundary = 'b1_' . $this->uniqueid;
            $body .= '--' . $boundary . self::CRLF;
            $body .= 'Content-Type: text/plain; charset=' . $this->CharSet . self::CRLF;
            $body .= 'Content-Transfer-Encoding: ' . $this->Encoding . self::CRLF;
            $body .= self::CRLF;
            $body .= $this->encodeString($this->AltBody, $this->Encoding) . self::CRLF;
            $body .= '--' . $boundary . self::CRLF;
            $body .= 'Content-Type: text/html; charset=' . $this->CharSet . self::CRLF;
            $body .= 'Content-Transfer-Encoding: ' . $this->Encoding . self::CRLF;
            $body .= self::CRLF;
            $body .= $this->encodeString($this->Body, $this->Encoding) . self::CRLF;
            $body .= '--' . $boundary . '--' . self::CRLF;
        } else {
            $body = $this->encodeString($this->Body, $this->Encoding);
        }

        return $body;
    }

    /**
     * Send email via SMTP
     */
    protected function sendMessageViaSMTP()
    {
        $smtp = new SMTP();
        $smtp->SMTPDebug = $this->SMTPDebug;
        $smtp->Timeout = $this->Timeout;

        $hosts = explode(';', $this->Host);
        $lastError = '';
        $connected = false;

        foreach ($hosts as $hostEntry) {
            $hostEntry = trim($hostEntry);

            $secure = $this->SMTPSecure;
            $port = $this->Port;

            // Handle scheme prefix
            if (strpos($hostEntry, '://') !== false) {
                $urlParts = parse_url($hostEntry);
                $secure = isset($urlParts['scheme']) ? $urlParts['scheme'] : $secure;
                $hostEntry = isset($urlParts['host']) ? $urlParts['host'] : $hostEntry;
                $port = isset($urlParts['port']) ? $urlParts['port'] : $port;
            } elseif (preg_match('/^(.+):([0-9]+)$/', $hostEntry, $m)) {
                $hostEntry = $m[1];
                $port = $m[2];
            }

            $options = $this->SMTPOptions;
            if ($secure === self::ENCRYPTION_SMTPS) {
                $prefix = 'ssl://';
            } else {
                $prefix = '';
            }

            if (!empty($prefix) && strpos($hostEntry, '://') === false) {
                $hostEntry = $prefix . $hostEntry;
            }

            if ($smtp->connect($hostEntry, $port, $this->Timeout, $options)) {
                $connected = true;
                break;
            }
            $err = $smtp->getError();
            $lastError = isset($err['error']) ? $err['error'] : 'Connection failed';
        }

        if (!$connected) {
            $this->setError('SMTP connect() failed: ' . $lastError);
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }

        $helo = $this->Helo ?: $this->serverHostname();

        if (!$smtp->hello($helo)) {
            $err = $smtp->getError();
            $this->setError('EHLO failed: ' . (isset($err['error']) ? $err['error'] : ''));
            $smtp->close();
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }

        // STARTTLS
        if ($this->SMTPSecure === self::ENCRYPTION_STARTTLS) {
            if (!$smtp->startTLS()) {
                $err = $smtp->getError();
                $this->setError('STARTTLS failed: ' . (isset($err['error']) ? $err['error'] : ''));
                $smtp->close();
                if ($this->exceptions) {
                    throw new Exception($this->ErrorInfo);
                }
                return false;
            }
            // Re-issue EHLO after STARTTLS
            if (!$smtp->hello($helo)) {
                $err = $smtp->getError();
                $this->setError('EHLO after STARTTLS failed: ' . (isset($err['error']) ? $err['error'] : ''));
                $smtp->close();
                if ($this->exceptions) {
                    throw new Exception($this->ErrorInfo);
                }
                return false;
            }
        }

        // Auth
        if ($this->SMTPAuth) {
            if (!$smtp->authenticate($this->Username, $this->Password, $this->AuthType)) {
                $err = $smtp->getError();
                $this->setError('SMTP authentication failed: ' . (isset($err['error']) ? $err['error'] : ''));
                $smtp->close();
                if ($this->exceptions) {
                    throw new Exception($this->ErrorInfo);
                }
                return false;
            }
        }

        // MAIL FROM
        if (!$smtp->mail($this->Sender ?: $this->From)) {
            $err = $smtp->getError();
            $this->setError('MAIL FROM failed: ' . (isset($err['error']) ? $err['error'] : ''));
            $smtp->close();
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }

        // RCPT TO - collect all recipients
        $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
        foreach ($allRecipients as $recipient) {
            if (!$smtp->recipient($recipient[0])) {
                $err = $smtp->getError();
                $this->setError('RCPT TO failed for ' . $recipient[0] . ': ' . (isset($err['error']) ? $err['error'] : ''));
                $smtp->close();
                if ($this->exceptions) {
                    throw new Exception($this->ErrorInfo);
                }
                return false;
            }
        }

        // DATA
        $message = $this->MIMEHeader . self::CRLF . $this->MIMEBody;
        if (!$smtp->data($message)) {
            $err = $smtp->getError();
            $this->setError('DATA failed: ' . (isset($err['error']) ? $err['error'] : ''));
            $smtp->close();
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }

        $smtp->quit();
        return true;
    }

    /**
     * Validate an email address
     */
    public function validateAddress($address, $patternselect = null)
    {
        if ($patternselect === null) {
            $patternselect = 'php';
        }

        switch ($patternselect) {
            case 'php':
            default:
                return (bool)filter_var($address, FILTER_VALIDATE_EMAIL);
        }
    }

    /**
     * Add address to internal list
     */
    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));

        $address = $this->punyencodeAddress($address);

        if (!$this->validateAddress($address)) {
            $this->setError('Invalid address: (' . $kind . ') ' . $address);
            return false;
        }

        $lc = strtolower($address);

        if ($kind !== 'Reply-To') {
            if (!isset($this->all_recipients[$lc])) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[$lc] = true;
            }
        } else {
            if (!isset($this->ReplyTo[$lc])) {
                $this->ReplyTo[$lc] = [$address, $name];
            }
        }

        return true;
    }

    /**
     * Format header line
     */
    protected function headerLine($name, $value)
    {
        return $name . ': ' . $value . self::CRLF;
    }

    /**
     * Format address list header
     */
    protected function addrAppend($type, $addresses)
    {
        $addresses_str = '';
        foreach ($addresses as $addr) {
            if ($addresses_str !== '') {
                $addresses_str .= ', ';
            }
            $addresses_str .= $this->addrFormat($addr);
        }
        return $this->headerLine($type, $addresses_str);
    }

    /**
     * Format single address as "Name <email>"
     */
    public function addrFormat($address)
    {
        if (empty($address[1])) {
            return $this->secureHeader($address[0]);
        }
        return $this->encodeHeader($this->secureHeader($address[1])) . ' <' . $this->secureHeader($address[0]) . '>';
    }

    /**
     * Encode header value for non-ASCII
     */
    public function encodeHeader($str, $position = 'text')
    {
        $matchcount = preg_match_all('/[\x80-\xFF]/', $str, $matches);
        if ($matchcount === 0) {
            return $str;
        }
        return '=?' . $this->CharSet . '?B?' . base64_encode($str) . '?=';
    }

    /**
     * Encode string with specified encoding
     */
    public function encodeString($str, $encoding = 'base64')
    {
        switch (strtolower($encoding)) {
            case 'base64':
                return chunk_split(base64_encode($str), 76, self::CRLF);
            case '7bit':
            case '8bit':
                return $str;
            case 'binary':
                return $str;
            case 'quoted-printable':
                return quoted_printable_encode($str);
            default:
                return $str;
        }
    }

    /**
     * Remove \r and \n from header values
     */
    public function secureHeader($str)
    {
        return trim(str_replace(["\r", "\n"], '', $str));
    }

    /**
     * Convert IDN to punycode if needed
     */
    protected function punyencodeAddress($address)
    {
        if (strpos($address, '@') !== false) {
            list($user, $domain) = explode('@', $address, 2);
            if (function_exists('idn_to_ascii') && preg_match('/[^\x00-\x7F]/', $domain)) {
                $punyDomain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
                if ($punyDomain !== false) {
                    return $user . '@' . $punyDomain;
                }
            }
        }
        return $address;
    }

    /**
     * Generate a unique message ID component
     */
    protected function generateId()
    {
        return md5(uniqid((string)time(), true));
    }

    /**
     * Get server hostname
     */
    protected function serverHostname()
    {
        $result = 'localhost';
        if (!empty($this->Helo)) {
            $result = $this->Helo;
        } elseif (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
            $result = $_SERVER['SERVER_NAME'];
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $result = $_SERVER['SERVER_ADDR'];
        }
        return $result;
    }

    /**
     * Get RFC compliant date string
     */
    public static function rfcDate()
    {
        $tz = date('Z');
        $tzs = ($tz < 0) ? '-' : '+';
        $tz = abs($tz);
        $tz = (int)($tz / 3600) * 100 + ($tz % 3600) / 60;
        return sprintf('%s %s%04d', date('D, j M Y H:i:s'), $tzs, $tz);
    }

    /**
     * Store error info
     */
    protected function setError($message)
    {
        $this->ErrorInfo = $message;
    }

    /**
     * Get all recipients as flat array
     */
    public function getAllRecipientAddresses()
    {
        $result = [];
        foreach (array_merge($this->to, $this->cc, $this->bcc) as $recipient) {
            $result[] = $recipient[0];
        }
        return $result;
    }

    /**
     * Get MIMEHeader
     */
    public function getMIMEHeader()
    {
        return $this->MIMEHeader;
    }

    /**
     * Get the full MIME message
     */
    public function getSentMIMEMessage()
    {
        return rtrim($this->MIMEHeader, self::CRLF) . self::CRLF . self::CRLF . $this->MIMEBody;
    }
}

class PHPMailerException extends \RuntimeException {}
