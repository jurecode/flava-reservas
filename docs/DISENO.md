# Sistema de diseño · Flava Studio

Reglas visuales del sistema. Si vas a agregar una pantalla, sigue esto y va a
verse como el resto sin esfuerzo.

---

## Las tres reglas

### 1. Geometría cuadrada

Nada de píldoras. Los radios son pequeños y consistentes:

| Token | Valor | Se usa en |
|---|---|---|
| `--radius-xs` | 3px | badges, puntos de estado, barras del gráfico |
| `--radius-sm` | 4px | ítems de menú, cajas de ícono, avatares chicos |
| `--radius` | 6px | botones, inputs, chips de fecha, slots |
| `--radius-lg` | 8px | tarjetas, modales, bloques de formulario |

### 2. Las tarjetas no llevan borde

Una tarjeta se separa del fondo **por contraste**, no por una línea:

```text
fondo de página   --canvas       #F1F0EC
tarjeta           --surface      #FFFFFF
tarjeta apagada   --surface-alt  #F8F7F4
```

```html
<div class="card">…</div>            <!-- correcto: sin borde -->
<div class="card" style="border:…">  <!-- no -->
```

Dentro de una tarjeta, las secciones se separan con una **hairline**
(`border-bottom: 1px solid var(--line)`), nunca con un recuadro completo.

### 3. El borde es para lo recalcado

Sólo lleva borde lo que necesita destacarse del resto:

| Situación | Clase |
|---|---|
| Opción **seleccionada** (servicio, barbero, fecha, hora, pago) | `.is-selected` → borde negro |
| Tarjeta que **exige atención** (hay una actualización disponible) | `.card-accent` → borde amarillo |
| **Zona destructiva** (rollback, cancelar) | `.card-danger` / `.danger-zone` → borde rojo |
| Tarjeta que debe leerse como **agrupada** aparte | `.card-outlined` → borde gris |
| **Campos de formulario** | el borde comunica "aquí se escribe" |
| Botón **secundario** (`.btn-ghost`) | el borde es su única forma |

Todo lo demás va sin borde.

---

## Íconos

**Nunca emoji en la interfaz.** Los emoji cambian según el sistema operativo, no
heredan el color del texto y desentonan con una tipografía de marca.

```php
<?= icon('calendar') ?>            <!-- 18px por defecto -->
<?= icon('scissors', 15) ?>        <!-- tamaño explícito -->
<?= icon('check', 14, 'mi-clase') ?>
```

Los 81 íconos viven en [`App\Support\Icon`](../app/Support/Icon.php): SVG en
línea sobre un grid de 24×24, trazo 1.75 y `currentColor`, así heredan el color
y el tamaño del contexto.

Cuando un ícono necesita peso visual propio, va dentro de una caja:

```html
<span class="ico-box"><?= icon('calendar', 15) ?></span>
<span class="ico-box ico-box-accent"><?= icon('scissors', 17) ?></span>
<span class="ico-box ico-box-dark"><?= icon('user', 17) ?></span>
```

La iconografía de dominio se decide en un solo lugar, no en las vistas:

```php
Icon::forPaymentMethod('transfer')   // 'bank'
Icon::forBookingStatus('completed')  // 'check-circle'
Icon::forFlash('error')              // 'alert'
```

**Única excepción:** los mensajes de WhatsApp al cliente sí llevan emoji
(`✂️ Corte + Barba`). Ahí son contenido del mensaje, no iconografía de interfaz,
y es lo que se espera en ese canal.

---

## Booking: una decisión por pantalla

El flujo de reserva es lo más importante del sistema. Cada paso muestra **una
sola decisión**, con opciones grandes y sin nada que compita por atención.

```text
Paso 1  servicio      .pick-list  →  filas de altura uniforme
Paso 2  barbero       .pick-list  →  incluye "cualquier barbero disponible"
Paso 3  fecha y hora  .date-strip + .slot-grid
Paso 4  checkout      .checkout-grid  →  dos columnas en escritorio
```

Detalles que sostienen la simplicidad:

- **Un toque basta**: al elegir una opción se avanza solo (`data-auto-advance`).
- **Altura uniforme**: `.pick-meta` va siempre en una línea con elipsis, para que
  todas las filas midan lo mismo y la lista se lea de un vistazo.
- **Lo ya elegido** se resume arriba en `.chosen`, con opción de cambiarlo.
- **En móvil el resumen va primero** en el checkout: el cliente confirma qué está
  reservando antes de escribir sus datos. En escritorio queda fijo a la derecha.
- **Lo secundario va a un modal**: las políticas de reserva no ocupan espacio en
  el checkout, se abren si el cliente quiere leerlas.

---

## Servicios con imagen

Los servicios se muestran como **tarjetas-imagen**: la foto ocupa la tarjeta
completa y el contenido va encima, sobre un degradado que garantiza el contraste
del texto sin importar qué foto se suba.

```php
<?php $showDesc = true; require View::path('components.service-tile'); ?>
<?php require View::path('components.service-row'); ?>  <!-- versión compacta -->
```

### La cuadrícula destacada

`.showcase` arma un layout editorial: la primera tarjeta manda y las otras la
acompañan.

```text
móvil            tablet              escritorio
┌───────┐        ┌───────────┐       ┌───────────┬─────┐
│   1   │        │     1     │       │           │  2  │
├───────┤        ├─────┬─────┤       │     1     ├─────┤
│   2   │        │  2  │  3  │       │           │  3  │
├───────┤        └─────┴─────┘       └───────────┴─────┘
│   3   │
└───────┘
```

La descripción sólo aparece en la tarjeta grande: en las chicas no cabe sin
apretar el resto.

### Portadas cuando no hay foto

Un servicio recién creado **no se ve roto**. `App\Support\Cover` genera una
portada con la identidad de la marca: degradado oscuro de una paleta curada,
textura de panal y el ícono del servicio en grande.

```php
Cover::render('corte-fade');                    // portada de servicio
Cover::initials('sebastian', 'SV');             // portada de barbero
```

Es determinista —el mismo servicio siempre se ve igual—, pesa cero porque va en
línea, y la paleta sale de cinco combinaciones oscuras que conviven con el
amarillo. No hay tonos al azar que puedan chocar con la marca.

### Subir fotos

Servicios y barberos tienen campo de imagen en su formulario de administración:

```php
$name    = 'image';
$current = $service['image'] ?? null;
$label   = 'Imagen del servicio';
$ratio   = 'wide';                   // 'tall' para barberos
require View::path('components.image-field');
```

El formulario debe llevar `enctype="multipart/form-data"`. El campo muestra
vista previa inmediata y una casilla para quitar la imagen actual.

`App\Services\UploadService` se encarga del resto:

- **El tipo se decide leyendo la imagen** (`getimagesize`), nunca por la
  extensión ni por el mime del navegador: ambos son falsificables.
- **El nombre lo genera el servidor**; el original se descarta.
- **La imagen se reprocesa con GD**, así el archivo guardado se reconstruye
  desde cero y no puede llevar código incrustado. Se reduce a 1200 px máximo.
- Al reemplazar, **la anterior se borra**: no quedan huérfanos.
- `/public/uploads` tiene el motor PHP apagado por `.htaccess`.

Tamaños recomendados: **1200×750** para servicios (horizontal) y **800×1000**
para barberos (vertical).

---

## Modales

Para lo que interrumpe: confirmar algo destructivo, leer un detalle largo, una
acción puntual sin cambiar de página.

```php
View::start('modals');
$id         = 'cancel-booking';
$modalTitle = 'Cancelar reserva';
$modalBody  = '<p>…</p>';
$modalFoot  = '<button class="btn btn-ghost btn-sm" data-modal-close>Volver</button>…';
require View::path('components.modal');
View::stop();
```

Se abre con `data-modal-open="cancel-booking"` desde cualquier botón. Se cierra
con `data-modal-close`, con Escape o con un clic en el fondo.

En móvil aparece como hoja inferior; en escritorio, centrado. Los layouts
`booking` y `panel` ya imprimen la sección `modals`.

---

## Componentes reutilizables

| Componente | Ruta |
|---|---|
| Mensajes flash | `components/flash.php` |
| Estado vacío | `components/empty.php` |
| Fila de reserva | `components/booking-row.php` |
| Botones de estado | `components/status-form.php` |
| Paginación | `components/pagination.php` |
| Modal | `components/modal.php` |

Ejemplo de estado vacío:

```php
$icon    = 'calendar';
$message = 'Sin reservas para hoy';
$hint    = 'Cuando entren reservas aparecerán aquí.';
require View::path('components.empty');
```

---

## Paleta

```text
Amarillo     #FFC400    acción principal, acentos, estado seleccionado
Miel         #E9A400    hover del amarillo, títulos de sección
Negro        #181818    texto, barra lateral, botón oscuro
Negro hondo  #0D0D0D    barras superiores, fondos de héroe
Blanco       #FFFDF5    fondo del sitio público
Gris         #F1F0EC    fondo de los paneles (--canvas)
```

Estados: verde `#2F9E44`, ámbar `#E9A400`, rojo `#E03131`, azul `#1C7ED6`.
Cada uno con su variante `-soft` para fondos.

El **panal** aparece sólo como textura de fondo (`.honeycomb`), a muy baja
opacidad. Es una referencia, no una decoración: la funcionalidad manda.

---

## Al agregar una pantalla

1. `.card` sin borde para los bloques de contenido.
2. Íconos con `icon()`, nunca emoji.
3. Radios desde los tokens, nada de valores sueltos.
4. Borde sólo si el elemento está seleccionado o exige atención.
5. Nada de `style="..."` para colores o fondos: usa las clases (`card-muted`,
   `card-accent`, `card-danger`, `ico-box-accent`).
6. Probar en 375px de ancho antes que en escritorio.
