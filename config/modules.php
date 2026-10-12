<?php

return [
    'attendance' => ['title' => 'Asistencias', 'icon' => 'checklist', 'description' => 'Registro por NFC, QR, enlace y lista manual; un resultado por estudiante y sesión.', 'roles' => ['coordinator', 'teacher', 'student']],
    'requests' => ['title' => 'Permisos', 'icon' => 'file', 'description' => 'Solicitudes de justificación, evidencias y resolución por el docente asignado.', 'roles' => ['coordinator', 'teacher', 'student']],
    'reports' => ['title' => 'Reportes', 'icon' => 'chart', 'description' => 'Seguimiento del 75 % y cierre de cómputos con reportes PDF versionados.', 'roles' => ['coordinator', 'teacher']],
    'credentials' => ['title' => 'Credenciales NFC', 'icon' => 'nfc', 'description' => 'Asignación, comprobación y revocación de etiquetas de estudiantes.', 'roles' => ['coordinator']],
    'imports' => ['title' => 'Importaciones', 'icon' => 'upload', 'description' => 'Carga de usuarios e inscripciones desde CSV, con revisión previa de errores.', 'roles' => ['coordinator']],
    'news' => ['title' => 'Noticias', 'icon' => 'bell', 'description' => 'Avisos académicos y notificaciones internas para cada grupo.', 'roles' => ['coordinator', 'teacher', 'student']],
];
