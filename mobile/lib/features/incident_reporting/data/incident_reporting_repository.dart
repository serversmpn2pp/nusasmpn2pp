import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/incident_reporting/data/incident_reporting_remote_data_source.dart';
import 'package:nusa/features/incident_reporting/domain/incident_reporting.dart';

final class IncidentReportingRepository {
  IncidentReportingRepository(this._remote);

  final IncidentReportingRemoteDataSource _remote;

  Future<IncidentReportReference> fetchReference() => _remote.fetchReference();

  Future<IncidentReportResult> submit(IncidentReportFormValue value) =>
      _remote.submit(value);
}

final incidentReportingRepositoryProvider =
    Provider<IncidentReportingRepository>(
      (ref) => IncidentReportingRepository(
        ref.watch(incidentReportingRemoteDataSourceProvider),
      ),
    );
