# Google Sign-In setup (Android OAuth client)

How to set up Google Cloud so that `app` can sign in with Google and `web`
accepts its ID tokens.

## How the client IDs fit together

You need **two OAuth clients in the same Google Cloud project**:

| Client          | Where it is used                                                         | Purpose                                                                                                   |
|-----------------|--------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------|
| **Web**         | `web` `OAUTH_GOOGLE_ID`, and `app` `google.serverClientId` | The ID token's audience (`aud`). The server only accepts tokens issued for this client.                   |
| **Android**     | Nowhere in code                                                           | Registers the pair *package name + signing certificate SHA-1*, so Google lets this app request tokens.   |

The app asks for a token **on behalf of the Web client** (`serverClientId`). Google checks the
caller against the Android client (package + SHA-1) and returns a token whose `aud` is the Web
client ID. `web` verifies that token against `OAUTH_GOOGLE_ID`.

> Do not put the Android client ID into the app. Using it as `serverClientId` is the most common
> cause of sign-in failures.

## 1. Pick the project and configure the consent screen

1. Open the [Google Cloud console](https://console.cloud.google.com/) and select the project that
   holds the existing **Web** client for `web`. If there is none yet, create a project.
2. Go to **Google Auth Platform** ([console.cloud.google.com/auth](https://console.cloud.google.com/auth/overview)).
   Older consoles have this under *APIs & Services → OAuth consent screen*.
3. **Branding**: set the app name, the user support email and the developer contact email.
4. **Audience**: choose *External*. While the app is in **Testing** status, only listed test
   users can sign in, so add your Google account(s) under **Test users**.
5. **Data Access**: the default scopes `openid`, `email` and `profile` are enough. These are
   non-sensitive, so no verification is needed.

## 2. Check the Web client

Under **Clients** (older consoles: *APIs & Services → Credentials*):

1. Find the **Web application** client that `web` uses, or create one
   (**Create client → Web application**).
2. Copy its **Client ID**, which looks like `1234567890-abc….apps.googleusercontent.com`.

For the "Sign in with Google" button of `web` (not needed for the app), the Web client also needs
**Authorized JavaScript origins**: e.g. `http://localhost:8000`, `https://localhost:8000` and the
production URL.

## 3. Get the SHA-1 of the signing certificate

Debug builds are signed with `~/.android/debug.keystore`. Get its SHA-1 with Gradle:

```shell
cd app
export JAVA_HOME=~/.local/share/JetBrains/Toolbox/apps/android-studio/jbr
./gradlew signingReport
```

Use the `SHA1:` line of `Variant: debug`. Alternatively use `keytool`:

```shell
keytool -list -v -keystore ~/.android/debug.keystore \
  -alias androiddebugkey -storepass android -keypass android
```

Every machine has its own debug keystore. Each developer either adds their own SHA-1 (one
Android client per SHA-1), or the team shares one debug keystore.

## 4. Create the Android client

**Clients → Create client**:

| Field                        | Value                                 |
|------------------------------|---------------------------------------|
| Application type             | **Android**                           |
| Name                         | e.g. `AgentTools debug`        |
| Package name                 | `com.elkuku.agenttools`                |
| SHA-1 certificate fingerprint | the debug SHA-1 from step 3          |

Save. You don't need to copy the resulting client ID. Changes can take a few minutes to take
effect.

**Release builds** need another Android client with the **release** key's SHA-1. If the app is
distributed through Google Play with Play App Signing, use the **app signing key** SHA-1 from
*Play Console → Test and release → App integrity*, not your upload key.

## 5. Configure both projects

Use the **Web** client ID from step 2 in both places.

`web/.env.local` (git-ignored):

```dotenv
OAUTH_GOOGLE_ID=1234567890-abc.apps.googleusercontent.com
```

`app/local.properties` (git-ignored, see `local.properties.example`):

```properties
google.serverClientId=1234567890-abc.apps.googleusercontent.com
server.baseUrl.debug=http://10.0.2.2:8000
```

Rebuild the app after changing `local.properties`, because the value is compiled into `BuildConfig`.

## 6. The user account

Signing in with the app (or the web login) registers an unknown Google account as a user, with
its email address as the identifier and `ROLE_USER`. It saves agent stats and custom medals right
away; the first stats set its agent name. To make yourself an admin, change the role in the
user CRUD (Admin → Agents → edit → Role), or create the user beforehand:

```shell
cd web
symfony console user-admin
# → Create User → Identifier: you@gmail.com → Role: ROLE_ADMIN
```

On the first request the server stores the account's Google ID on that user.

## 7. Try it

```shell
cd web && symfony serve --no-tls --allow-all-ip --port=8000
```

Start an emulator with a **Google Play** system image and add your Google account under
*Settings → Passwords & accounts*. Then install the app (`./gradlew installDebug`) and sign in.
Share your all-time stats from Ingress with the app and save them. They appear at
<http://localhost:8000/agent-stats>.

## Troubleshooting

| Symptom                                                                 | Likely cause                                                                                                                 |
|-------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| Sign-in fails right away ("developer console is not set up correctly", `DEVELOPER_ERROR`, error 10, or `NoCredentialException` although an account exists) | Package name or SHA-1 doesn't match an Android client, `serverClientId` is the Android client ID instead of the Web one, or the clients are in different projects. Wait a few minutes after changes. |
| "No Google account available on this device."                           | No Google account on the device, or the emulator image has no Google Play services.                                         |
| "Access blocked" / app not verified                                     | Consent screen is in Testing and the account is not a test user.                                                            |
| `google.serverClientId is not set in local.properties.`                 | Fill in `local.properties` and rebuild.                                                                                     |
| Saving or loading returns `401 Invalid ID token.`                       | `OAUTH_GOOGLE_ID` on the server differs from the app's `google.serverClientId` (audience mismatch), or the server clock is off. |
| Saving returns `422` "This agent name is already taken."                | Another user has the stats' agent name (ignoring case); an admin changes it in the user CRUD.                              |
| Saving or loading returns `401 Email address is not verified.`          | The Google account's email is not verified.                                                                                 |
| Network error / connection refused                                      | Server not started with `--allow-all-ip`, wrong port, or TLS enabled (the debug app uses plain HTTP to `10.0.2.2`).          |
