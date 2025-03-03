<?php

/**
 * Fetches an option value from the admin_options table by its name.
 *
 * @param string $name The option name to retrieve.
 * @return string|null The option value or null if not found.
 */
function getAdminOption($name) {
    global $conn; // Use the global PDO connection variable.

    try {
        $stmt = $conn->prepare("SELECT value FROM admin_options WHERE name = :name LIMIT 1");
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['value'] ?? null;
    } catch (PDOException $e) {
        error_log("Database error in getAdminOption: " . $e->getMessage());
        return null;
    }
}


/**
 * Mailer Class
 * Handles sending emails via SMTP without external libraries.
 */
class Mailer {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpEncryption;
    private $mailFrom;
    private $mailFromName;

    /**
     * Constructor to initialize SMTP settings.
     */
    public function __construct() {
        // Fetch SMTP configurations from admin_options
        $this->smtpHost = getAdminOption('SMTP_HOST');
        $this->smtpPort = getAdminOption('SMTP_PORT');
        $this->smtpUsername = getAdminOption('SMTP_USERNAME');
        $this->smtpPassword = getAdminOption('SMTP_PASSWORD');
        $this->smtpEncryption = strtolower(getAdminOption('SMTP_ENCRYPTION')); // 'tls' or 'ssl'
        $this->mailFrom = getAdminOption('MAIL_FROM');
        $this->mailFromName = getAdminOption('MAIL_FROM_NAME');

        // Validate SMTP configurations
        if (!$this->smtpHost || !$this->smtpPort || !$this->smtpUsername || !$this->smtpPassword || !$this->smtpEncryption || !$this->mailFrom || !$this->mailFromName) {
            throw new Exception("SMTP configuration is incomplete. Please check the admin_options table.");
        }
    }

    /**
     * Sends an email using SMTP.
     *
     * @param string $to Recipient email address.
     * @param string $toName Recipient name.
     * @param string $subject Email subject.
     * @param string $body Email body (HTML allowed).
     * @param string $altBody Alternative plain-text body.
     * @return bool|string Returns true on success, or an error message string on failure.
     */
    public function sendEmail($to, $toName, $subject, $body, $altBody = '') {
        // Open a socket connection to the SMTP server
        $socket = fsockopen(
            ($this->smtpEncryption === 'ssl' ? 'ssl://' : '') . $this->smtpHost,
            $this->smtpPort,
            $errno,
            $errstr,
            10
        );

        if (!$socket) {
            return "Connection failed: $errstr ($errno)";
        }

        // Function to get server response
        $getResponse = function($socket) {
            $response = '';
            while ($str = fgets($socket, 515)) { // 512 bytes plus CRLF
                $response .= $str;
                if (substr($str, 3, 1) == ' ') { break; } // If the 4th character is a space, it's the end of the response
            }
            return $response;
        };

        // Read initial server response
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return "Error after connection: $response";
        }

        // Send EHLO
        $hostname = 'localhost'; // You can set your server's hostname
        fputs($socket, "EHLO $hostname\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return "Error after EHLO: $response";
        }

        // Initiate encryption if TLS
        if ($this->smtpEncryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = $getResponse($socket);
            if (substr($response, 0, 3) != '220') {
                fclose($socket);
                return "Error initiating TLS: $response";
            }

            // Enable crypto on the stream
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return "Failed to enable TLS encryption.";
            }

            // Send EHLO again after TLS
            fputs($socket, "EHLO $hostname\r\n");
            $response = $getResponse($socket);
            if (substr($response, 0, 3) != '250') {
                fclose($socket);
                return "Error after EHLO (post-TLS): $response";
            }
        }

        // Authentication
        fputs($socket, "AUTH LOGIN\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return "Error after AUTH LOGIN: $response";
        }

        // Send base64 encoded username
        fputs($socket, base64_encode($this->smtpUsername) . "\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return "Error after sending username: $response";
        }

        // Send base64 encoded password
        fputs($socket, base64_encode($this->smtpPassword) . "\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            return "Error after sending password: $response";
        }

        // MAIL FROM
        fputs($socket, "MAIL FROM:<{$this->mailFrom}>\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return "Error after MAIL FROM: $response";
        }

        // RCPT TO
        fputs($socket, "RCPT TO:<{$to}>\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '250' && substr($response, 0, 3) != '251') {
            fclose($socket);
            return "Error after RCPT TO: $response";
        }

        // DATA
        fputs($socket, "DATA\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return "Error after DATA: $response";
        }

        // Prepare email headers and body
        $headers  = "From: " . $this->encodeHeader($this->mailFromName) . " <{$this->mailFrom}>\r\n";
        $headers .= "To: " . $this->encodeHeader($toName) . " <{$to}>\r\n";
        $headers .= "Subject: " . $this->encodeHeader($subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "\r\n"; // End of headers

        // Send headers and body
        fputs($socket, $headers . $body . "\r\n.\r\n");
        $response = $getResponse($socket);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return "Error after sending message data: $response";
        }

        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    }

    /**
     * Encodes headers to handle special characters.
     *
     * @param string $str The string to encode.
     * @return string The encoded string.
     */
    private function encodeHeader($str) {
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }
}
?>