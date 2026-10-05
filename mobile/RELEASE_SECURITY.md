# Android release verification

Release tasks require an existing keystore outside this checkout. There is no
debug fallback. Supply either the following environment variables or
`KARTAR_SIGNING_PROPERTIES`, pointing to a private properties file outside Git:

| Environment variable | Properties key |
|---|---|
| KARTAR_RELEASE_STORE_FILE | storeFile |
| KARTAR_RELEASE_STORE_PASSWORD | storePassword |
| KARTAR_RELEASE_KEY_ALIAS | keyAlias |
| KARTAR_RELEASE_KEY_PASSWORD | keyPassword |

Never commit keys/passwords or include them in command arguments/build logs.
Use restricted file permissions and a protected CI secret store. Properties
paths should use forward slashes on Windows. Release manifests prohibit
cleartext and trust system certificate authorities. Only debug resource
overlays permit HTTP to localhost, 127.0.0.1 and the emulator's 10.0.2.2.

Before distributing, operators must verify the final APK/AAB signer using
`apksigner verify --print-certs` or the equivalent bundle certificate check,
and match the protected distribution signing identity. A locally generated
test identity verifies the build pipeline only. Never distribute that artifact.

The previously distributed debug-signed APK cannot be assumed to update to a
different signing identity. Test the actual installed-device/update path and
choose the distribution transition with the operator. Uninstall/reinstall can
lose app data; no automatic migration is claimed. Register the real release
certificate with Firebase/API providers when required. Provisioning,
distribution, HTTPS endpoints and real-device network QA require review.

References: [Android signing](https://developer.android.com/studio/publish/app-signing)
and [network security configuration](https://developer.android.com/privacy-and-security/security-config).
