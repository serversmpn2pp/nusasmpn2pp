import 'package:flutter_riverpod/flutter_riverpod.dart';

final passwordChangeGateProvider =
    NotifierProvider<PasswordChangeGateController, bool>(
      PasswordChangeGateController.new,
    );

class PasswordChangeGateController extends Notifier<bool> {
  @override
  bool build() => false;

  void requireChange() => state = true;

  void setRequired(bool required) => state = required;

  void clear() => state = false;
}
