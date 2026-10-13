import 'dart:io';

import 'package:flutter/services.dart';
import 'package:path_provider/path_provider.dart';

const _downloadsChannel = MethodChannel('com.aabhushan.erp/downloads');

Future<String> saveDownloadedFile(
  Uint8List bytes,
  String fileName, {
  String? folderName,
}) async {
  if (Platform.isAndroid) {
    final path = await _downloadsChannel.invokeMethod<String>('savePdf', {
      'bytes': bytes,
      'fileName': fileName,
      'folderName': folderName,
    });
    if (path == null || path.isEmpty) {
      throw StateError('Android did not return the saved PDF location.');
    }
    return path;
  }

  final directory = await getApplicationDocumentsDirectory();
  final segments = [
    directory.path,
    'downloads',
    if (folderName != null && folderName.trim().isNotEmpty) folderName.trim(),
  ];
  final downloadDir = Directory(segments.join(Platform.pathSeparator));
  if (!await downloadDir.exists()) {
    await downloadDir.create(recursive: true);
  }
  final file = File('${downloadDir.path}${Platform.pathSeparator}$fileName');
  await file.writeAsBytes(bytes, flush: true);
  return file.path;
}
