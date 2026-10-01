# PWA presentation

`main.dart` selects this presentation layer on the web with `kIsWeb`. Native app
screens remain in `jewellery_mobile`.

PWA screens, theme, navigation and upload controls belong here. API clients,
session storage, models and notification services remain shared from
`jewellery_mobile`; preserve their contracts when editing this layer.

The UI bundles Manrope and Cormorant Garamond fonts from Google Fonts under the
SIL Open Font License. Their licenses are included in `assets/fonts` and registered
with Flutter's license registry. The PWA-only files live under `web/fonts`, so
native packages do not bundle them. PWA install icons are maintained in `web/icons`.
