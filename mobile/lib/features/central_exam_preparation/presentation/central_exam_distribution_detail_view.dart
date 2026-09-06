import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_document_service.dart';
import 'package:nusa/features/central_exam_preparation/application/central_exam_preparation_controller.dart';
import 'package:nusa/features/central_exam_preparation/domain/central_exam_preparation.dart';

class CentralExamDistributionDetailView extends ConsumerStatefulWidget {
  const CentralExamDistributionDetailView({
    required this.eventId,
    required this.groupId,
    super.key,
  });

  final int eventId;
  final int groupId;

  @override
  ConsumerState<CentralExamDistributionDetailView> createState() =>
      _CentralExamDistributionDetailViewState();
}

class _CentralExamDistributionDetailViewState
    extends ConsumerState<CentralExamDistributionDetailView> {
  bool _documentBusy = false;

  @override
  Widget build(BuildContext context) {
    final request = (eventId: widget.eventId, groupId: widget.groupId);
    final state = ref.watch(centralExamDistributionDetailProvider(request));
    final detail = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pembagian Peserta'),
        actions: [
          if (detail != null && detail.participantCount > 0)
            IconButton(
              tooltip: 'Cetak dan bagikan dokumen',
              onPressed: _documentBusy ? null : () => _openDocuments(detail),
              icon: _documentBusy
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.picture_as_pdf_outlined),
            ),
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading || _documentBusy
                ? null
                : () => ref.invalidate(
                    centralExamDistributionDetailProvider(request),
                  ),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _message(error),
            onRetry: () =>
                ref.invalidate(centralExamDistributionDetailProvider(request)),
          ),
          data: (detail) => _DistributionBody(detail: detail),
        ),
      ),
    );
  }

  Future<void> _openDocuments(CentralExamDistributionDetail detail) async {
    final action = await showModalBottomSheet<_DocumentAction>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => _DocumentSheet(detail: detail),
    );
    if (action == null || !mounted) return;

    setState(() => _documentBusy = true);
    try {
      final documents = ref.read(centralExamDocumentServiceProvider);
      final completed = switch ((action.room, action.share)) {
        (null, false) => documents.printParticipantList(detail),
        (null, true) => documents.shareParticipantList(detail),
        (final room?, false) => documents.printDeskLabels(detail, room),
        (final room?, true) => documents.shareDeskLabels(detail, room),
      };
      final result = await completed;
      if (result && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              action.share
                  ? 'Dokumen siap dibagikan.'
                  : 'Dokumen dikirim ke layanan cetak.',
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Dokumen gagal dibuat: ${_message(error)}')),
        );
      }
    } finally {
      if (mounted) setState(() => _documentBusy = false);
    }
  }
}

class _DocumentAction {
  const _DocumentAction({required this.share, this.room});
  final bool share;
  final CentralExamDistributionRoom? room;
}

class _DocumentSheet extends StatelessWidget {
  const _DocumentSheet({required this.detail});
  final CentralExamDistributionDetail detail;

  @override
  Widget build(BuildContext context) => FractionallySizedBox(
    heightFactor: 0.78,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Dokumen Pembagian Peserta',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              SizedBox(height: 4),
              Text(
                'Cetak langsung atau bagikan berkas PDF melalui aplikasi lain.',
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 12),
              ),
            ],
          ),
        ),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
            children: [
              _DocumentCard(
                icon: Icons.groups_rounded,
                title: 'Daftar Seluruh Peserta',
                subtitle:
                    '${detail.participantCount} peserta · ${detail.rooms.length} ruang',
                onPrint: () =>
                    Navigator.pop(context, const _DocumentAction(share: false)),
                onShare: () =>
                    Navigator.pop(context, const _DocumentAction(share: true)),
              ),
              const Padding(
                padding: EdgeInsets.fromLTRB(4, 18, 4, 8),
                child: Text(
                  'LABEL MEJA PER RUANG',
                  style: TextStyle(
                    color: NusaColors.primary,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.7,
                  ),
                ),
              ),
              for (final room in detail.rooms)
                if (room.participants.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _DocumentCard(
                      icon: Icons.badge_outlined,
                      title: '${room.code} · ${room.name}',
                      subtitle:
                          '${room.participants.length} label · 8 label per lembar A4',
                      onPrint: () => Navigator.pop(
                        context,
                        _DocumentAction(share: false, room: room),
                      ),
                      onShare: () => Navigator.pop(
                        context,
                        _DocumentAction(share: true, room: room),
                      ),
                    ),
                  ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _DocumentCard extends StatelessWidget {
  const _DocumentCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onPrint,
    required this.onShare,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onPrint;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.1)),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.06),
          blurRadius: 14,
          offset: const Offset(0, 5),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: NusaColors.primary, size: 21),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: onPrint,
                icon: const Icon(Icons.print_outlined, size: 18),
                label: const Text('Cetak'),
              ),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: FilledButton.icon(
                onPressed: onShare,
                icon: const Icon(Icons.share_outlined, size: 18),
                label: const Text('Bagikan'),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _DistributionBody extends StatelessWidget {
  const _DistributionBody({required this.detail});
  final CentralExamDistributionDetail detail;

  @override
  Widget build(BuildContext context) => CustomScrollView(
    slivers: [
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        sliver: SliverToBoxAdapter(child: _Header(detail: detail)),
      ),
      if (detail.rooms.isEmpty)
        const SliverFillRemaining(
          hasScrollBody: false,
          child: Center(child: Text('Pembagian peserta belum dibangkitkan.')),
        )
      else
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
          sliver: SliverList.builder(
            itemCount: detail.rooms.length,
            itemBuilder: (context, index) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: _RoomCard(room: detail.rooms[index]),
            ),
          ),
        ),
    ],
  );
}

class _Header extends StatelessWidget {
  const _Header({required this.detail});
  final CentralExamDistributionDetail detail;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(19),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          detail.eventCode,
          style: const TextStyle(
            color: NusaColors.accent,
            fontSize: 10,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          detail.eventName,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          'Tingkat ${detail.grade} · ${detail.sessionName} · ${detail.timeLabel}',
          style: const TextStyle(color: Colors.white70, fontSize: 10.5),
        ),
        Text(
          '${detail.classNames.join(', ')} · ${detail.participantCount}/${detail.totalCapacity} kursi',
          style: const TextStyle(color: Colors.white70, fontSize: 10.5),
        ),
      ],
    ),
  );
}

class _RoomCard extends StatelessWidget {
  const _RoomCard({required this.room});
  final CentralExamDistributionRoom room;
  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: ExpansionTile(
      initiallyExpanded: true,
      leading: CircleAvatar(
        backgroundColor: NusaColors.primary.withValues(alpha: 0.1),
        child: Text(
          room.code,
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 9,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      title: Text(
        room.name,
        style: const TextStyle(fontWeight: FontWeight.w900),
      ),
      subtitle: Text(
        '${room.occupiedCount}/${room.capacity} kursi${room.location?.trim().isNotEmpty == true ? ' · ${room.location}' : ''}',
        style: const TextStyle(fontSize: 10.5),
      ),
      children: [
        const Divider(height: 1),
        if (room.participants.isEmpty)
          const Padding(
            padding: EdgeInsets.all(18),
            child: Text('Belum ada peserta di ruang ini.'),
          )
        else
          for (final participant in room.participants)
            _ParticipantTile(participant: participant),
      ],
    ),
  );
}

class _ParticipantTile extends StatelessWidget {
  const _ParticipantTile({required this.participant});
  final CentralExamDistributedParticipant participant;
  @override
  Widget build(BuildContext context) => ListTile(
    dense: true,
    leading: CircleAvatar(
      radius: 16,
      backgroundColor: NusaColors.surfaceBlue,
      child: Text(
        '${participant.seatNumber}',
        style: const TextStyle(
          color: NusaColors.primary,
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    ),
    title: Text(
      participant.name,
      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
    ),
    subtitle: Text(
      '${participant.className} · ${participant.nisn ?? 'NISN belum tersedia'}\n${participant.seatCode}',
      style: const TextStyle(fontSize: 9.5),
    ),
    isThreeLine: true,
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 52),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton(onPressed: onRetry, child: const Text('Coba Lagi')),
        ],
      ),
    ),
  );
}

String _message(Object error) => error is AppException
    ? error.message
    : 'Pembagian peserta belum dapat dimuat.';
