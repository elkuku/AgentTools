# El sitio web

[agent-tools.elkuku.dev](https://agent-tools.elkuku.dev) muestra la misma cuenta que la app: tus
estadísticas con medallas, tu línea de tiempo, tus banners y los demás agentes. Las subidas vienen
de la app; el sitio web también puede importar estadísticas que guardaste en otro lugar.

## Iniciar sesión {#sign-in}

![La página de inicio de sesión](/screenshots/web/login.webp){.shot-web}

Haz clic en **Iniciar sesión con Google** y elige la misma cuenta de Google que en la app. La
primera vez, el sitio web te lleva a tu perfil para que elijas lo que ven otros usuarios.

La barra de navegación tiene tus estadísticas, **Mis banners** y **Agentes**. A la derecha: tu foto
de perfil con el menú de usuario (perfil, **Importar estadísticas**, cerrar sesión), el idioma
(inglés, alemán, español) y el tema claro u oscuro.

## Inicio {#home}

![La página de inicio](/screenshots/web/home.webp){.shot-web}

La página de inicio te da la bienvenida, enlaza tus estadísticas de agente y tiene el enlace de
descarga de la app.

## Estadísticas de agente {#agent-stats}

![Mis estadísticas de agente](/screenshots/web/my-stats.webp){.shot-web}

**Estadísticas de agente** muestra tu última subida como la app: agente, facción y nivel, meses de
C.O.R.E, AP, cada medalla con su nivel, Dronenet y Kinetics, tus medallas especiales y resúmenes de
tus banners y de tu línea de tiempo.

Haz clic en una medalla para ver sus detalles: la estadística detrás, tu valor y el valor de cada
nivel.

![Los detalles de una medalla](/screenshots/web/medal-details.webp){.shot-web}

## Línea de tiempo {#timeline}

![La línea de tiempo](/screenshots/web/timeline.webp){.shot-web}

La línea de tiempo (**Estadísticas de agente → Línea de tiempo**) tiene tus subidas con lo que
cambió, tus eventos y viajes y tus banners con fecha, lo más reciente primero, debajo de un gráfico
de tus AP. Funciona como la [línea de tiempo de la app](/es/guide/app#timeline): los mismos filtros
y un rango de fechas que acerca el gráfico.

**Añadir evento** abre un formulario para un evento nuevo: su día, un día de fin opcional para un
viaje y qué pasó. Los botones del lápiz y de la papelera cambian o eliminan un evento; en un
banner, el lápiz cambia el día en que lo hiciste.

![Añadir un evento](/screenshots/web/timeline-add-event.webp){.shot-web}

## Importar estadísticas {#import-stats}

![Importar estadísticas](/screenshots/web/import.webp){.shot-web}

Menú de usuario → **Importar estadísticas** importa estadísticas que guardaste antes de AgentTools,
p. ej. en 4e-website (allí: menú de la cuenta → **Migrate**):

- **Estadísticas en CSV** (**Export CSV**): una fila de encabezado con los nombres de columna
  (`datetime`, `nickname`, `faction`, `ap`, …) y una fila por subida. Se omiten las estadísticas que
  ya tienes y solo se importan las de tu agente.
- **Medallas especiales en JSON** (**Export custom medals**): se combinan con las tuyas; una medalla
  que ya tienes conserva el nivel más alto.

La página informa de lo que importó, lo que omitió y lo que no reconoció.

## Mis banners {#my-banners}

![Mis banners](/screenshots/web/banners.webp){.shot-web}

**Mis banners** muestra los banners que la app sincronizó desde Bannergress (solo la app puede
sincronizarlos): un mapa con un punto donde empieza cada banner y las listas de banners publicados,
completados y pendientes. Los botones debajo del mapa muestran u ocultan cada lista. En la lista de
completados, indica el día en que hiciste un banner para ponerlo en tu línea de tiempo.

## Agentes {#agents}

![La lista de agentes](/screenshots/web/agents.webp){.shot-web}

**Agentes** lista a los agentes que muestran su perfil. Haz clic en uno para ver las partes que
muestra.

![El perfil de un agente](/screenshots/web/agent-profile.webp){.shot-web}

## Perfil {#profile}

![La página de perfil](/screenshots/web/profile.webp){.shot-web}

El perfil (menú de usuario → **Perfil**) muestra tu identificador y tu nombre de agente y la
visibilidad de cada parte de tu perfil; consulta [Privacidad](/es/guide/privacy). Los cambios aquí
y en los ajustes de la app son el mismo ajuste.

## Para administradores {#for-admins}

Los administradores tienen el menú **Admin**:

- **Agentes**: cada usuario con su foto, nombre de agente, rol, lo que muestra y su nivel. Los
  administradores pueden añadir, editar y eliminar usuarios, p. ej. para corregir un nombre de
  agente.
- **Acerca de** y **Registros**: la información del sistema y el registro de despliegue.
- La página de perfil muestra un token de la API para el botón **Authorize** de la
  [documentación de la API](https://agent-tools.elkuku.dev/api/docs).
