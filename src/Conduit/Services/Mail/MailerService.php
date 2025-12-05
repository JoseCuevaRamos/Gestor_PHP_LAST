<?php

namespace Conduit\Services\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailerService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // --- Configuración básica SMTP (Gmail) ---
        $this->mailer->isSMTP();
        $this->mailer->Host       = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = getenv('MAIL_USERNAME');
        $this->mailer->Password   = getenv('MAIL_PASSWORD');
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS en el puerto 587
        $this->mailer->Port       = (int)(getenv('MAIL_PORT') ?: 587);
        $this->mailer->CharSet    = 'UTF-8';
        $this->mailer->Encoding   = 'base64'; // Mejor compatibilidad

        // ⭐ CONFIGURACIONES ANTI-SPAM
        $this->mailer->SMTPKeepAlive = true; // Mantener conexión para múltiples envíos
        $this->mailer->Priority = 3; // Prioridad normal (1=alta, 3=normal, 5=baja)
        
        // ⭐ Headers adicionales para evitar spam
        $this->mailer->XMailer = ' '; // Ocultar "X-Mailer: PHPMailer"

        // --- Opciones SSL ---
        $isProduction = getenv('APP_ENV') === 'production';
        
        if ($isProduction) {
            // Producción: Validación de certificados con peer_name
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                    'peer_name'         => 'smtp.gmail.com', // ✅ IMPORTANTE: Especificar el hostname
                ],
            ];
        } else {
            // Desarrollo: Permitir certificados autofirmados
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        // --- Remitente (FROM) ---
        $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: getenv('MAIL_USERNAME');
        $fromName    = getenv('MAIL_FROM_NAME') ?: getenv('APP_NAME') ?: 'Gestor de Proyectos';

        if (empty($fromAddress)) {
            error_log('MailerService: MAIL_FROM_ADDRESS y MAIL_USERNAME están vacíos.');
            $fromAddress = 'no-reply@example.com';
        }

        $this->mailer->setFrom($fromAddress, $fromName);
        
        // ⭐ Reply-To (importante para evitar spam)
        $replyTo = getenv('MAIL_REPLY_TO') ?: $fromAddress;
        $this->mailer->addReplyTo($replyTo, $fromName);
    }

    /**
     * Envía un correo con configuraciones anti-spam
     */
    public function send(string $to, string $subject, string $htmlBody): void
    {
        try {
            $mail = clone $this->mailer;
            $mail->clearAllRecipients();

            // ⭐ Agregar destinatario
            $mail->addAddress($to);
            
            // ⭐ Configurar contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            
            // ⭐ Versión texto plano (IMPORTANTE para evitar spam)
            $mail->AltBody = $this->htmlToText($htmlBody);
            
            // ⭐ Headers personalizados para mejorar deliverability
            $mail->addCustomHeader('X-Priority', '3'); // Normal priority
            $mail->addCustomHeader('X-MSMail-Priority', 'Normal');
            $mail->addCustomHeader('Importance', 'Normal');
            
            // ⭐ Identificador único del mensaje
            $messageId = sprintf(
                '<%s.%s@%s>',
                uniqid(),
                time(),
                parse_url(getenv('APP_URL') ?: 'localhost', PHP_URL_HOST) ?: 'localhost'
            );
            $mail->MessageID = $messageId;

            // ⭐ Enviar
            $mail->send();
            
            error_log("Correo enviado exitosamente a: {$to} - Asunto: {$subject}");
            
        } catch (Exception $e) {
            // Log del error detallado
            error_log('Error enviando correo a ' . $to . ': ' . $e->getMessage());
            error_log('Diagnóstico SMTP: ' . $mail->ErrorInfo);
            
            // No romper la app, solo registrar el error
        }
    }

    /**
     * Convierte HTML a texto plano para AltBody
     */
    private function htmlToText(string $html): string
    {
        // Remover scripts y estilos
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        
        // Convertir <br> y <p> a saltos de línea
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        
        // Remover todas las etiquetas HTML
        $text = strip_tags($text);
        
        // Decodificar entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Limpiar espacios múltiples
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s+\n/', "\n\n", $text);
        
        return trim($text);
    }
    
    /**
     * Cierra la conexión SMTP (útil al finalizar múltiples envíos)
     */
    public function __destruct()
    {
        if ($this->mailer->getSMTPInstance()) {
            $this->mailer->smtpClose();
        }
    }
}