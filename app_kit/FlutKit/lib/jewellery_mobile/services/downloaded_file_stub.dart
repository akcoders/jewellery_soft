import 'dart:typed_data';

Future<String> saveDownloadedFile(
  Uint8List bytes,
  String fileName, {
  String? folderName,
}) {
  throw UnsupportedError('File downloads are not supported on this platform.');
}
