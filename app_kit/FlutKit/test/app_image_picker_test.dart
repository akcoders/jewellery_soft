import 'package:flutkit/jewellery_pwa/services/app_image_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';

class _FakeImagePicker extends ImagePicker {
  ImageSource? source;
  int calls = 0;
  bool multiple = false;
  int? quality;
  double? width;
  bool? metadata;
  Object? error;
  List<XFile> images = [XFile('proof.jpg')];

  @override
  Future<XFile?> pickImage({
    required ImageSource source,
    double? maxWidth,
    double? maxHeight,
    int? imageQuality,
    CameraDevice preferredCameraDevice = CameraDevice.rear,
    bool requestFullMetadata = true,
  }) async {
    calls++;
    this.source = source;
    quality = imageQuality;
    width = maxWidth;
    metadata = requestFullMetadata;
    if (error != null) throw error!;
    return images.firstOrNull;
  }

  @override
  Future<List<XFile>> pickMultiImage({
    double? maxWidth,
    double? maxHeight,
    int? imageQuality,
    int? limit,
    bool requestFullMetadata = true,
  }) async {
    calls++;
    source = ImageSource.gallery;
    multiple = true;
    quality = imageQuality;
    width = maxWidth;
    metadata = requestFullMetadata;
    if (error != null) throw error!;
    return images;
  }
}

Future<void> _openPicker(
  WidgetTester tester,
  _FakeImagePicker picker, {
  bool multiple = false,
  required ValueChanged<List<XFile>> onResult,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: Builder(
          builder: (context) => TextButton(
            onPressed: () async {
              if (multiple) {
                onResult(
                  await AppImagePicker(picker: picker).pickImages(context),
                );
              } else {
                final image = await AppImagePicker(
                  picker: picker,
                ).pickImage(context, imageQuality: 82);
                onResult(image == null ? [] : [image]);
              }
            },
            child: const Text('Add proof'),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Add proof'));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('camera selection keeps proof image settings', (tester) async {
    final picker = _FakeImagePicker();
    List<XFile>? result;
    await _openPicker(tester, picker, onResult: (value) => result = value);
    await tester.tap(find.text('Open camera'));
    await tester.pumpAndSettle();

    expect(picker.source, ImageSource.camera);
    expect(picker.multiple, isFalse);
    expect(picker.quality, 82);
    expect(picker.width, 1800);
    expect(picker.metadata, isFalse);
    expect(result!.single.name, 'proof.jpg');
  });

  testWidgets('single proof may come from gallery', (tester) async {
    final picker = _FakeImagePicker();
    List<XFile>? result;
    await _openPicker(tester, picker, onResult: (value) => result = value);
    await tester.tap(find.text('Choose from gallery'));
    await tester.pumpAndSettle();

    expect(picker.source, ImageSource.gallery);
    expect(picker.multiple, isFalse);
    expect(result!.single.name, 'proof.jpg');
  });

  testWidgets('order gallery keeps multiple images', (tester) async {
    final picker = _FakeImagePicker()..images.add(XFile('design.png'));
    List<XFile>? result;
    await _openPicker(
      tester,
      picker,
      multiple: true,
      onResult: (value) => result = value,
    );
    await tester.tap(find.text('Choose from gallery'));
    await tester.pumpAndSettle();

    expect(picker.multiple, isTrue);
    expect(result!.map((image) => image.name), ['proof.jpg', 'design.png']);
    expect(picker.quality, 85);
  });

  testWidgets('multiple upload can also capture one camera image', (
    tester,
  ) async {
    final picker = _FakeImagePicker();
    List<XFile>? result;
    await _openPicker(
      tester,
      picker,
      multiple: true,
      onResult: (value) => result = value,
    );
    await tester.tap(find.text('Open camera'));
    await tester.pumpAndSettle();

    expect(picker.source, ImageSource.camera);
    expect(picker.multiple, isFalse);
    expect(result!.length, 1);
  });

  testWidgets('closing source chooser does not open a device picker', (
    tester,
  ) async {
    final picker = _FakeImagePicker();
    List<XFile>? result;
    await _openPicker(tester, picker, onResult: (value) => result = value);
    await tester.tap(find.byTooltip('Close photo options'));
    await tester.pumpAndSettle();

    expect(picker.calls, 0);
    expect(result, isEmpty);
  });

  testWidgets('cancelling device picker returns no replacement photo', (
    tester,
  ) async {
    final picker = _FakeImagePicker()..images = [];
    List<XFile>? result;
    await _openPicker(tester, picker, onResult: (value) => result = value);
    await tester.tap(find.text('Open camera'));
    await tester.pumpAndSettle();

    expect(result, isEmpty);
    expect(tester.takeException(), isNull);
  });

  testWidgets('permission errors show a recoverable message', (tester) async {
    final picker = _FakeImagePicker()
      ..error = PlatformException(code: 'camera_access_denied');
    List<XFile>? result;
    await _openPicker(tester, picker, onResult: (value) => result = value);
    await tester.tap(find.text('Open camera'));
    await tester.pumpAndSettle();

    expect(result, isEmpty);
    expect(find.textContaining('Photo access is blocked.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('source choices fit a narrow screen with enlarged text', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    tester.platformDispatcher.textScaleFactorTestValue = 1.5;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await _openPicker(tester, _FakeImagePicker(), onResult: (_) {});
    expect(find.text('Open camera'), findsOneWidget);
    expect(find.text('Choose from gallery'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
