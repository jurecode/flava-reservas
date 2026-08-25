<?php
/**
 * Ruta: /app/Views/reception/bookings/show.php
 * Recepción reutiliza la vista de administración: misma interfaz, mismas rutas
 * base parametrizadas ($basePath). No duplicamos plantillas.
 */

require \Core\View::path('admin.bookings.show');
