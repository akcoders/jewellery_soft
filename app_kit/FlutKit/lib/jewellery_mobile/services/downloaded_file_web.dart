// ignore_for_file: deprecated_member_use

import 'dart:async';
import 'dart:html' as html;
import 'dart:typed_data';

Future<String> saveDownloadedFile(Uint8List bytes, String fileName) async {
  final blob = html.Blob(<dynamic>[bytes], 'application/pdf');
  final url = html.Url.createObjectUrlFromBlob(blob);
  final anchor = html.AnchorElement(href: url)
    ..download = fileName
    ..style.display = 'none';
  html.document.body?.append(anchor);
  anchor.click();
  anchor.remove();
  Timer(const Duration(seconds: 1), () => html.Url.revokeObjectUrl(url));
  return fileName;
}
