export 'downloaded_file_stub.dart'
    if (dart.library.io) 'downloaded_file_native.dart'
    if (dart.library.html) 'downloaded_file_web.dart';
