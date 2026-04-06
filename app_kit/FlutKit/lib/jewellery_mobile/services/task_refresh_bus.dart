import 'package:flutter/foundation.dart';

class TaskRefreshBus {
  TaskRefreshBus._();

  static final ValueNotifier<int> tick = ValueNotifier<int>(0);

  static void notify() {
    tick.value = tick.value + 1;
  }
}
