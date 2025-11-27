<?php

namespace Conduit\Services\Mail;

use Conduit\Models\Tarea;
use Carbon\Carbon;

class NotificacionTareaService
{
    private MailerService $mailer;

    public function __construct(?MailerService $mailer = null)
    {
        $this->mailer = $mailer ?? new MailerService();
    }

    /**
     * Correo cuando se asigna una tarea a un usuario.
     */
    public function enviarAsignacion(Tarea $tarea): void
    {
        $tarea->loadMissing(['asignado', 'proyecto', 'columna', 'creador']);

        $usuario = $tarea->asignado;
        if (!$usuario || empty($usuario->correo)) {
            return;
        }

        $proyectoNombre = optional($tarea->proyecto)->nombre ?? 'Proyecto';
        $columnaNombre  = optional($tarea->columna)->nombre ?? 'Sin columna';
        $creadorNombre  = optional($tarea->creador)->nombre ?? 'Un compañero';
        $dueText        = $tarea->due_at 
            ? Carbon::parse($tarea->due_at)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : 'Sin fecha límite';

        $urlTarea = getenv('FRONTEND_URL') 
            ? getenv('FRONTEND_URL') . '/proyectos/' . $tarea->id_proyecto . '/tareas/' . $tarea->id_tarea
            : 'http://localhost:4200/proyectos/' . $tarea->id_proyecto . '/tareas/' . $tarea->id_tarea;

        $appName = getenv('APP_NAME') ?: 'Gestor de Proyectos';
        $subject = "[{$appName}] Nueva tarea asignada: {$tarea->titulo}";

        $prioridadColor = $this->obtenerColorPrioridad($tarea->prioridad);
        $prioridadTexto = $this->obtenerTextoPrioridad($tarea->prioridad);

        $body = $this->generarPlantillaHTML([
            'titulo' => 'Nueva Tarea Asignada',
            'icono' => '📋',
            'saludo' => "Estimado/a {$usuario->nombre},",
            'mensaje_principal' => "Se le ha asignado una nueva tarea que requiere su atención.",
            'contenido' => "
                <table style='width: 100%; border-collapse: collapse; margin: 25px 0; background-color: #ffffff; border: 1px solid #e0e0e0;'>
                    <tr style='background-color: #f5f5f5;'>
                        <td colspan='2' style='padding: 15px; border-bottom: 2px solid #2c5aa0;'>
                            <h3 style='margin: 0; color: #2c5aa0; font-size: 16px; font-weight: 600;'>{$tarea->titulo}</h3>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Asignado por</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333; font-size: 14px;'>{$creadorNombre}</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Proyecto</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333; font-size: 14px;'>{$proyectoNombre}</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Estado actual</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333; font-size: 14px;'>{$columnaNombre}</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Prioridad</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0;'>
                            <span style='display: inline-block; padding: 4px 12px; background-color: {$prioridadColor}; color: white; border-radius: 3px; font-size: 12px; font-weight: 600; text-transform: uppercase;'>{$prioridadTexto}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Fecha límite</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: " . ($tarea->due_at ? '#d32f2f' : '#666') . "; font-weight: " . ($tarea->due_at ? '600' : 'normal') . "; font-size: 14px;'>{$dueText}</td>
                    </tr>
                    " . ($tarea->descripcion ? "
                    <tr>
                        <td style='padding: 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px; vertical-align: top;'>Descripción</td>
                        <td style='padding: 15px; border-bottom: 1px solid #e0e0e0; color: #333; font-size: 14px; line-height: 1.6;'>" . nl2br($this->e($tarea->descripcion)) . "</td>
                    </tr>
                    " : "") . "
                </table>
                
                <div style='margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #2c5aa0; border-radius: 4px;'>
                    <p style='margin: 0; color: #555; font-size: 14px; line-height: 1.5;'>
                        <strong>Nota:</strong> Acceda a su tablero de proyectos para gestionar esta tarea y colaborar con su equipo.
                    </p>
                </div>
            ",
            'pie' => "Este es un mensaje automático del sistema de gestión de proyectos. Por favor, no responda a este correo.",
        ]);

        $this->mailer->send($usuario->correo, $subject, $body);
    }

    /**
     * Correo cuando la tarea vence mañana.
     */
    public function enviarRecordatorioVencimiento(Tarea $tarea): void
    {
        $tarea->loadMissing(['asignado', 'proyecto', 'columna']);

        $usuario = $tarea->asignado;
        if (!$usuario || empty($usuario->correo)) {
            return;
        }

        $proyectoNombre = optional($tarea->proyecto)->nombre ?? 'Proyecto';
        $columnaNombre  = optional($tarea->columna)->nombre ?? 'Sin columna';
        $due            = Carbon::parse($tarea->due_at);
        $dueText        = $due->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        $urlTarea = getenv('FRONTEND_URL') 
            ? getenv('FRONTEND_URL') . '/proyectos/' . $tarea->id_proyecto . '/tareas/' . $tarea->id_tarea
            : 'http://localhost:4200/proyectos/' . $tarea->id_proyecto . '/tareas/' . $tarea->id_tarea;

        $appName = getenv('APP_NAME') ?: 'Gestor de Proyectos';
        $subject = "[{$appName}] Recordatorio: \"{$tarea->titulo}\" vence mañana";

        $prioridadColor = $this->obtenerColorPrioridad($tarea->prioridad);
        $prioridadTexto = $this->obtenerTextoPrioridad($tarea->prioridad);

        $body = $this->generarPlantillaHTML([
            'titulo' => 'Recordatorio de Vencimiento',
            'icono' => '⏰',
            'saludo' => "Estimado/a {$usuario->nombre},",
            'mensaje_principal' => "Le recordamos que la siguiente tarea tiene fecha de vencimiento para <strong style='color: #d32f2f;'>mañana, {$dueText}</strong>.",
            'contenido' => "
                <div style='background-color: #fff9e6; border: 1px solid #ffd54f; border-radius: 4px; padding: 20px; margin: 25px 0;'>
                    <div style='margin-bottom: 15px;'>
                        <span style='display: inline-block; background-color: #ff6f00; color: white; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>VENCE MAÑANA</span>
                    </div>
                    <h3 style='margin: 0 0 10px 0; color: #e65100; font-size: 16px; font-weight: 600;'>⚠️ Atención Requerida</h3>
                    <p style='margin: 0; color: #f57c00; font-size: 14px;'>Esta tarea requiere su atención inmediata.</p>
                </div>
                
                <table style='width: 100%; border-collapse: collapse; margin: 25px 0; background-color: #ffffff; border: 1px solid #e0e0e0;'>
                    <tr style='background-color: #f5f5f5;'>
                        <td colspan='2' style='padding: 15px; border-bottom: 2px solid #d32f2f;'>
                            <h3 style='margin: 0; color: #d32f2f; font-size: 16px; font-weight: 600;'>{$tarea->titulo}</h3>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Proyecto</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333; font-size: 14px;'>{$proyectoNombre}</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Estado actual</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; color: #333; font-size: 14px;'>{$columnaNombre}</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Prioridad</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0;'>
                            <span style='display: inline-block; padding: 4px 12px; background-color: {$prioridadColor}; color: white; border-radius: 3px; font-size: 12px; font-weight: 600; text-transform: uppercase;'>{$prioridadTexto}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0; width: 140px; color: #666; font-weight: 600; font-size: 14px;'>Fecha de vencimiento</td>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0;'>
                            <span style='color: #d32f2f; font-weight: 700; font-size: 15px;'>🗓️ {$dueText}</span>
                        </td>
                    </tr>
                </table>
                
                <div style='margin-top: 20px; padding: 15px; background-color: #e3f2fd; border-left: 4px solid #1976d2; border-radius: 4px;'>
                    <p style='margin: 0; color: #0d47a1; font-size: 14px; line-height: 1.5;'>
                        <strong>💡 Recordatorio:</strong> Si ya ha completado esta tarea, por favor márquela como finalizada en el tablero para mantener el seguimiento actualizado del proyecto.
                    </p>
                </div>
            ",
            'pie' => "Este es un mensaje automático del sistema de gestión de proyectos. Por favor, no responda a este correo.",
        ]);

        $this->mailer->send($usuario->correo, $subject, $body);
    }

    /**
     * Genera una plantilla HTML profesional para correos
     */
    private function generarPlantillaHTML(array $params): string
    {
        $titulo = $params['titulo'] ?? 'Notificación';
        $icono = $params['icono'] ?? '📋';
        $saludo = $params['saludo'] ?? '';
        $mensajePrincipal = $params['mensaje_principal'] ?? '';
        $contenido = $params['contenido'] ?? '';
        $pie = $params['pie'] ?? '';

        $appName = getenv('APP_NAME') ?: 'Gestor de Proyectos';
        $year = date('Y');

        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$titulo}</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background-color: #f5f5f5;'>
            <table role='presentation' style='width: 100%; border-collapse: collapse; background-color: #f5f5f5; padding: 40px 0;'>
                <tr>
                    <td style='padding: 0 20px;'>
                        <table role='presentation' style='max-width: 650px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e0e0e0;'>
                            
                            <!-- Header -->
                            <tr>
                                <td style='background-color: #2c5aa0; padding: 25px 40px; border-bottom: 4px solid #1e3a5f;'>
                                    <table role='presentation' style='width: 100%;'>
                                        <tr>
                                            <td style='vertical-align: middle;'>
                                                <div style='font-size: 32px; line-height: 1;'>{$icono}</div>
                                            </td>
                                            <td style='vertical-align: middle; padding-left: 15px;'>
                                                <h1 style='margin: 0; color: #ffffff; font-size: 20px; font-weight: 600; letter-spacing: -0.3px;'>{$titulo}</h1>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style='padding: 40px;'>
                                    <p style='margin: 0 0 15px 0; color: #333; font-size: 15px; line-height: 1.5;'>
                                        {$saludo}
                                    </p>
                                    
                                    <p style='margin: 0 0 25px 0; color: #555; font-size: 15px; line-height: 1.6;'>
                                        {$mensajePrincipal}
                                    </p>
                                    
                                    {$contenido}
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style='background-color: #f9f9f9; padding: 30px 40px; border-top: 1px solid #e0e0e0;'>
                                    <p style='margin: 0 0 8px 0; color: #888; font-size: 13px; line-height: 1.5;'>
                                        {$pie}
                                    </p>
                                    <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 15px 0;'>
                                    <p style='margin: 0; color: #aaa; font-size: 12px; text-align: center;'>
                                        © {$year} <strong>{$appName}</strong>. Todos los derechos reservados.
                                    </p>
                                </td>
                            </tr>
                            
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }

    /**
     * Obtiene el color según la prioridad
     */
    private function obtenerColorPrioridad(string $prioridad): string
    {
        $prioridad = strtolower($prioridad);
        
        return match($prioridad) {
            'alta', 'high' => '#d32f2f',      // Rojo oscuro
            'media', 'medium' => '#f57c00',   // Naranja
            'baja', 'low' => '#388e3c',       // Verde oscuro
            default => '#616161',             // Gris oscuro
        };
    }

    /**
     * Obtiene el texto formateado de la prioridad
     */
    private function obtenerTextoPrioridad(string $prioridad): string
    {
        $prioridad = strtolower($prioridad);
        
        return match($prioridad) {
            'alta', 'high' => 'Alta',
            'media', 'medium' => 'Media',
            'baja', 'low' => 'Baja',
            default => ucfirst($prioridad),
        };
    }

    /**
     * Escapa HTML para seguridad
     */
    private function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}