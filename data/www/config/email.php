<?php

// Inbucket nastavitve (znotraj Docker omrežja)
define('SMTP_HOST', 'inbucket');
define('SMTP_PORT', 2500);
define('SMTP_USER', ''); // Inbucket ne potrebuje avtentikacije
define('SMTP_PASS', '');

// Pošiljatelj
define('EMAIL_FROM', 'noreply@todomanager.si');
define('EMAIL_FROM_NAME', 'Todo Manager');

/**
 * Pošlje e-poštno sporočilo preko Inbucket SMTP
 * 
 * @param string $to Prejemnikov email naslov
 * @param string $subject Zadeva sporočila
 * @param string $message Vsebina sporočila (HTML)
 * @return bool Uspešnost pošiljanja
 */
function poslji_email($to, $subject, $message) {
    $smtp = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    
    if (!$smtp) {
        error_log("SMTP povezava neuspešna: $errstr ($errno)");
        return false;
    }
    
    try {
        // Preberi začetni odgovor
        $response = fgets($smtp, 515);
        if (strpos($response, '220') === false) {
            throw new Exception("SMTP strežnik ni pripravljen: $response");
        }
        
        // EHLO
        fputs($smtp, "EHLO " . gethostname() . "\r\n");
        
        // Branje večvrstičnega odgovora od EHLO
        do {
            $response = fgets($smtp, 515);
        } while(substr($response, 3, 1) != ' ');

        if (substr($response, 0, 3) != '250') {
            throw new Exception("EHLO neuspešen: $response");
        }
        
        // MAIL FROM
        fputs($smtp, "MAIL FROM: <" . EMAIL_FROM . ">\r\n");
        $response = fgets($smtp, 515);
        if (strpos($response, '250') === false) {
            throw new Exception("MAIL FROM neuspešen: $response");
        }
        
        // RCPT TO
        fputs($smtp, "RCPT TO: <$to>\r\n");
        $response = fgets($smtp, 515);
        if (strpos($response, '250') === false) {
            throw new Exception("RCPT TO neuspešen: $response");
        }
        
        // DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);
        if (strpos($response, '354') === false) {
            throw new Exception("DATA neuspešen: $response");
        }
        
        // Headers & Body
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";
        
        fputs($smtp, "$headers\r\n$message\r\n.\r\n");
        $response = fgets($smtp, 515);
        if (strpos($response, '250') === false) {
            throw new Exception("Pošiljanje vsebine neuspešno: $response");
        }
        
        // QUIT
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Napaka pri pošiljanju emaila: " . $e->getMessage());
        if ($smtp) fclose($smtp);
        return false;
    }
}
?>
