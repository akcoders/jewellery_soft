import 'dart:convert';
import 'dart:typed_data';

import 'package:flutkit/jewellery_pwa/widgets/app_photo_field.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';

Uint8List _photoBytes() => base64Decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg==',
);

Future<void> _show(WidgetTester tester, Widget child) => tester.pumpWidget(
  MaterialApp(
    home: Scaffold(body: SingleChildScrollView(child: child)),
  ),
);

void main() {
  testWidgets('selected photo can be inspected, replaced and removed', (
    tester,
  ) async {
    var picked = 0;
    var removed = 0;
    await _show(
      tester,
      AppPhotoField(
        file: XFile.fromData(
          _photoBytes(),
          path: 'proof.png',
          name: 'proof.png',
        ),
        onPick: () => picked++,
        onRemove: () => removed++,
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('proof.png'), findsOneWidget);
    await tester.tap(find.byTooltip('View proof.png'));
    await tester.pumpAndSettle();
    expect(find.byType(InteractiveViewer), findsOneWidget);
    expect(find.text('Pinch to zoom and check details'), findsOneWidget);

    await tester.tap(find.byTooltip('Close photo preview'));
    await tester.pumpAndSettle();
    expect(find.byType(InteractiveViewer), findsNothing);
    await tester.tap(find.text('Replace photo'));
    await tester.tap(find.byTooltip('Remove proof.png'));
    expect(picked, 1);
    expect(removed, 1);
    expect(tester.takeException(), isNull);
  });

  testWidgets('opening picker disables replacement and removal', (
    tester,
  ) async {
    var changes = 0;
    await _show(
      tester,
      AppPhotoField(
        file: XFile.fromData(
          _photoBytes(),
          path: 'proof.png',
          name: 'proof.png',
        ),
        busy: true,
        onPick: () => changes++,
        onRemove: () => changes++,
      ),
    );
    await tester.pump();
    expect(find.text('Opening photos…'), findsOneWidget);
    expect(find.byTooltip('Remove proof.png'), findsNothing);
    await tester.tap(find.text('Opening photos…'));
    expect(changes, 0);
  });

  testWidgets('saving disables photo changes', (tester) async {
    var changes = 0;
    await _show(
      tester,
      AppPhotoField(
        file: XFile.fromData(
          _photoBytes(),
          path: 'proof.png',
          name: 'proof.png',
        ),
        enabled: false,
        onPick: () => changes++,
        onRemove: () => changes++,
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byTooltip('Remove proof.png'), findsNothing);
    await tester.tap(find.text('Replace photo'));
    expect(changes, 0);
  });

  testWidgets(
    'document attachments retain remove action without photo preview',
    (tester) async {
      var removed = false;
      await _show(
        tester,
        AppAttachmentTile(name: 'design.pdf', onRemove: () => removed = true),
      );
      expect(find.byTooltip('View design.pdf'), findsNothing);
      await tester.tap(find.byTooltip('Remove design.pdf'));
      expect(removed, isTrue);
    },
  );

  testWidgets('photo actions fit a narrow display with enlarged text', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    tester.platformDispatcher.textScaleFactorTestValue = 1.5;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await _show(
      tester,
      AppAttachmentTile(
        name: 'a very long jewellery reference photo filename.png',
        imageBytes: _photoBytes(),
        onRemove: () {},
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(
      find.byTooltip('View a very long jewellery reference photo filename.png'),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}
