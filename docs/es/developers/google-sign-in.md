# Configurar el inicio de sesión con Google (cliente OAuth de Android)

Cómo configurar Google Cloud para que `app` pueda iniciar sesión con Google y `web` acepte sus
tokens de ID.

## Cómo encajan los ID de cliente {#how-the-client-ids-fit-together}

Necesitas **dos clientes OAuth en el mismo proyecto de Google Cloud**:

| Cliente         | Dónde se usa                                                             | Para qué                                                                                                  |
|-----------------|--------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------|
| **Web**         | `OAUTH_GOOGLE_ID` de `web` y `google.serverClientId` de `app`            | La audiencia (`aud`) del token de ID. El servidor solo acepta tokens emitidos para este cliente.          |
| **Android**     | En ningún lugar del código                                               | Registra el par *nombre del paquete + SHA-1 del certificado de firma*, para que Google permita a esta app pedir tokens. |

La app pide un token **en nombre del cliente Web** (`serverClientId`). Google comprueba quién llama
con el cliente de Android (paquete + SHA-1) y devuelve un token cuyo `aud` es el ID de cliente Web.
`web` verifica ese token con `OAUTH_GOOGLE_ID`.

> No pongas el ID de cliente de Android en la app. Usarlo como `serverClientId` es la causa más
> común de fallos al iniciar sesión.

## 1. Elegir el proyecto y configurar la pantalla de consentimiento {#_1-pick-the-project-and-configure-the-consent-screen}

1. Abre la [consola de Google Cloud](https://console.cloud.google.com/) y elige el proyecto que
   tiene el cliente **Web** existente de `web`. Si aún no hay ninguno, crea un proyecto.
2. Ve a **Google Auth Platform** ([console.cloud.google.com/auth](https://console.cloud.google.com/auth/overview)).
   En consolas más antiguas está en *APIs & Services → OAuth consent screen*.
3. **Branding**: define el nombre de la app, el correo de soporte al usuario y el correo de contacto
   del desarrollador.
4. **Audience**: elige *External*. Mientras la app esté en estado **Testing**, solo pueden iniciar
   sesión los usuarios de prueba de la lista, así que añade tu(s) cuenta(s) de Google en
   **Test users**.
5. **Data Access**: los scopes por defecto `openid`, `email` y `profile` son suficientes. No son
   sensibles, así que no hace falta verificación.

## 2. Revisar el cliente Web {#_2-check-the-web-client}

En **Clients** (consolas antiguas: *APIs & Services → Credentials*):

1. Busca el cliente **Web application** que usa `web`, o crea uno
   (**Create client → Web application**).
2. Copia su **Client ID**, que tiene el aspecto de `1234567890-abc….apps.googleusercontent.com`.

Para el botón «Sign in with Google» de `web` (no hace falta para la app), el cliente Web también
necesita **Authorized JavaScript origins**: p. ej. `http://localhost:8000`,
`https://localhost:8000` y la URL de producción.

## 3. Obtener el SHA-1 del certificado de firma {#_3-get-the-sha-1-of-the-signing-certificate}

Las compilaciones de depuración se firman con `~/.android/debug.keystore`. Obtén su SHA-1 con
Gradle:

```shell
cd app
export JAVA_HOME=~/.local/share/JetBrains/Toolbox/apps/android-studio/jbr
./gradlew signingReport
```

Usa la línea `SHA1:` de `Variant: debug`. Otra opción es `keytool`:

```shell
keytool -list -v -keystore ~/.android/debug.keystore \
  -alias androiddebugkey -storepass android -keypass android
```

Cada máquina tiene su propio almacén de claves de depuración. O cada desarrollador añade su propio
SHA-1 (un cliente de Android por SHA-1), o el equipo comparte un almacén de claves de depuración.

## 4. Crear el cliente de Android {#_4-create-the-android-client}

**Clients → Create client**:

| Campo                        | Valor                                 |
|------------------------------|---------------------------------------|
| Application type             | **Android**                           |
| Name                         | p. ej. `AgentTools debug`             |
| Package name                 | `com.elkuku.agenttools`               |
| SHA-1 certificate fingerprint | el SHA-1 de depuración del paso 3    |

Guarda. No necesitas copiar el ID de cliente resultante. Los cambios pueden tardar unos minutos en
aplicarse.

Las **compilaciones de release** necesitan otro cliente de Android con el SHA-1 de la clave de
**release**. Si la app se distribuye por Google Play con Play App Signing, usa el SHA-1 de la
**clave de firma de la app** de *Play Console → Test and release → App integrity*, no tu clave de
subida.

## 5. Configurar ambos proyectos {#_5-configure-both-projects}

Usa el ID de cliente **Web** del paso 2 en ambos sitios.

`web/.env.local` (ignorado por git):

```dotenv
OAUTH_GOOGLE_ID=1234567890-abc.apps.googleusercontent.com
```

`app/local.properties` (ignorado por git, consulta `local.properties.example`):

```properties
google.serverClientId=1234567890-abc.apps.googleusercontent.com
server.baseUrl.debug=http://10.0.2.2:8000
```

Vuelve a compilar la app después de cambiar `local.properties`, porque el valor se compila en
`BuildConfig`.

## 6. La cuenta de usuario {#_6-the-user-account}

Iniciar sesión con la app (o en la web) registra una cuenta de Google desconocida como usuario, con
su correo como identificador y `ROLE_USER`. Guarda estadísticas de agente y medallas especiales al
instante; las primeras estadísticas fijan su nombre de agente. Para hacerte administrador, cambia
el rol en la administración de usuarios (Admin → Agentes → editar → Rol), o crea el usuario antes:

```shell
cd web
symfony console user-admin
# → Create User → Identifier: you@gmail.com → Role: ROLE_ADMIN
```

En la primera petición, el servidor guarda el ID de Google de la cuenta en ese usuario.

## 7. Probarlo {#_7-try-it}

```shell
cd web && symfony serve --no-tls --allow-all-ip --port=8000
```

Inicia un emulador con una imagen de sistema **Google Play** y añade tu cuenta de Google en
*Ajustes → Contraseñas y cuentas*. Luego instala la app (`./gradlew installDebug`) e inicia sesión.
Comparte tus estadísticas de siempre desde Ingress con la app y guárdalas. Aparecen en
`http://localhost:8000/agent-stats`.

## Solución de problemas {#troubleshooting}

| Síntoma                                                                 | Causa probable                                                                                                               |
|-------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| El inicio de sesión falla al instante («developer console is not set up correctly», `DEVELOPER_ERROR`, error 10 o `NoCredentialException` aunque existe una cuenta) | El nombre del paquete o el SHA-1 no coinciden con ningún cliente de Android, `serverClientId` es el ID de cliente de Android en vez del Web, o los clientes están en proyectos distintos. Espera unos minutos después de los cambios. |
| «No hay ninguna cuenta de Google disponible en este dispositivo.»       | No hay cuenta de Google en el dispositivo, o la imagen del emulador no tiene servicios de Google Play.                      |
| «Access blocked» / app no verificada                                    | La pantalla de consentimiento está en Testing y la cuenta no es un usuario de prueba.                                       |
| `google.serverClientId is not set in local.properties.`                 | Rellena `local.properties` y vuelve a compilar.                                                                             |
| Guardar o cargar devuelve `401 Invalid ID token.`                       | `OAUTH_GOOGLE_ID` en el servidor es distinto del `google.serverClientId` de la app (audiencia incorrecta), o el reloj del servidor va mal. |
| Guardar devuelve `422` «This agent name is already taken.»              | Otro usuario tiene el nombre de agente de las estadísticas (sin distinguir mayúsculas); un administrador lo cambia en la administración de usuarios. |
| Guardar o cargar devuelve `401 Email address is not verified.`          | El correo de la cuenta de Google no está verificado.                                                                        |
| Error de red / conexión rechazada                                       | El servidor no se inició con `--allow-all-ip`, puerto incorrecto o TLS activado (la app de depuración usa HTTP simple a `10.0.2.2`). |
