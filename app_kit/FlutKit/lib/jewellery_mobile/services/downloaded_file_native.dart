import 'dart:io';
import 'dart:typed_data';

import 'package:path_provider/path_provider.dart';

Future<String> saveDownloadedFile(Uint8List bytes, String fileName) async {
  final directory = await getApplicationDocumentsDirectory();
  final downloadDir = Directory(
    '${directory.path}${Platform.pathSeparator}downloads',
  );
  if (!await downloadDir.exists()) {
    await downloadDir.create(recursive: true);
  }
  final file = File('${downloadDir.path}${Platform.pathSeparator}$fileName');
  await file.writeAsBytes(bytes, flush: true);
  return file.path;
}
