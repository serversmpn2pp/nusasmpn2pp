import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/incident_reporting/data/incident_reporting_repository.dart';
import 'package:nusa/features/incident_reporting/domain/incident_reporting.dart';

class IncidentReportingController
    extends AsyncNotifier<IncidentReportReference> {
  @override
  Future<IncidentReportReference> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(_fetch);
  }

  Future<IncidentReportReference> _fetch() async {
    try {
      return await ref
          .read(incidentReportingRepositoryProvider)
          .fetchReference();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final incidentReportingControllerProvider =
    AsyncNotifierProvider.autoDispose<
      IncidentReportingController,
      IncidentReportReference
    >(IncidentReportingController.new);

final incidentReportingActionsProvider = Provider<IncidentReportingActions>(
  IncidentReportingActions.new,
);

class IncidentReportingActions {
  IncidentReportingActions(this._ref);

  final Ref _ref;

  Future<IncidentReportResult> submit(IncidentReportFormValue value) async {
    try {
      return await _ref.read(incidentReportingRepositoryProvider).submit(value);
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
