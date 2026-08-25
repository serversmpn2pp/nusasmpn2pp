import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/class_promotion/domain/class_promotion.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class PromotionSummaryCard extends StatelessWidget {
  const PromotionSummaryCard({required this.summary, super.key});

  final PromotionSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.14),
          blurRadius: 16,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Siswa asal', value: summary.sourceStudents),
        _SummaryItem(label: 'Sudah masuk', value: summary.alreadyPlaced),
        _SummaryItem(label: 'Belum masuk', value: summary.notPlaced),
        _SummaryItem(label: 'Kelas tujuan', value: summary.destinationClasses),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.label, required this.value});

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 19,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 9,
          ),
        ),
      ],
    ),
  );
}

class PromotionNoticeCard extends StatelessWidget {
  const PromotionNoticeCard({required this.messages, super.key});

  final List<String> messages;

  @override
  Widget build(BuildContext context) {
    final hasWarning = messages.isNotEmpty;
    final color = hasWarning ? NusaColors.accent : NusaColors.primaryLight;
    final effectiveMessages = hasWarning
        ? messages
        : const [
            'Penempatan dibuat pada tahun tujuan. Data kelas asal tetap tersimpan.',
          ];

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        border: Border.all(color: color.withValues(alpha: 0.28)),
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            hasWarning ? Icons.info_outline_rounded : Icons.sync_alt_rounded,
            color: hasWarning ? NusaColors.textPrimary : NusaColors.primary,
            size: 21,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (var index = 0; index < effectiveMessages.length; index++)
                  Padding(
                    padding: EdgeInsets.only(
                      bottom: index == effectiveMessages.length - 1 ? 0 : 5,
                    ),
                    child: Text(
                      effectiveMessages[index],
                      style: const TextStyle(fontSize: 12, height: 1.35),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class DestinationCapacityList extends StatelessWidget {
  const DestinationCapacityList({required this.classes, super.key});

  final List<PromotionClass> classes;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 67,
    child: ListView.separated(
      scrollDirection: Axis.horizontal,
      itemCount: classes.length,
      separatorBuilder: (context, index) => const SizedBox(width: 8),
      itemBuilder: (context, index) {
        final item = classes[index];
        final full = item.remainingCapacity == 0;
        return Container(
          constraints: const BoxConstraints(minWidth: 126),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
          decoration: BoxDecoration(
            color: full
                ? NusaColors.accent.withValues(alpha: 0.09)
                : NusaColors.surfaceBlue,
            border: Border.all(
              color: full
                  ? NusaColors.accent.withValues(alpha: 0.35)
                  : NusaColors.outline,
            ),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                item.name,
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                full ? '${item.occupancyLabel} · penuh' : item.occupancyLabel,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10,
                ),
              ),
            ],
          ),
        );
      },
    ),
  );
}

class PromotionMemberCard extends StatelessWidget {
  const PromotionMemberCard({
    required this.member,
    required this.destinationClasses,
    required this.value,
    required this.onChanged,
    required this.enabled,
    super.key,
  });

  final PromotionMember member;
  final List<PromotionClass> destinationClasses;
  final int? value;
  final ValueChanged<int?> onChanged;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final identity = [
      if (member.student.nis != null && member.student.nis!.isNotEmpty)
        'NIS ${member.student.nis}',
      if (member.student.nisn != null && member.student.nisn!.isNotEmpty)
        'NISN ${member.student.nisn}',
    ].join(' · ');
    final currentClass = member.currentPlacement?.schoolClass.name;

    return Card(
      key: Key('promotion-member-${member.id}'),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 21,
                  backgroundColor: NusaColors.surfaceBlue,
                  child: Text(
                    member.student.initials,
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                    ),
                  ),
                ),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        member.student.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      if (identity.isNotEmpty)
                        Text(
                          identity,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10,
                          ),
                        ),
                    ],
                  ),
                ),
                if (member.attendanceNumber != null)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '#${member.attendanceNumber}',
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
              ],
            ),
            if (currentClass != null) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(
                    Icons.check_circle_rounded,
                    size: 15,
                    color: NusaColors.success,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      'Saat ini sudah di $currentClass pada tahun tujuan',
                      style: const TextStyle(
                        color: NusaColors.success,
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ],
            const SizedBox(height: 11),
            NusaDropdownField<int?>(
              fieldKey: Key('promotion-target-${member.id}'),
              value: value,
              enabled: enabled,
              decoration: const InputDecoration(
                labelText: 'Kelas tujuan',
                prefixIcon: Icon(Icons.trending_up_rounded),
              ),
              options: [
                const NusaDropdownOption<int?>(
                  value: null,
                  label: 'Lewati (tidak diubah)',
                ),
                for (final item in destinationClasses)
                  NusaDropdownOption<int?>(
                    value: item.id,
                    label: '${item.name} · ${item.occupancyLabel}',
                    enabled:
                        item.remainingCapacity != 0 ||
                        member.currentPlacement?.schoolClass.id == item.id,
                  ),
              ],
              onChanged: onChanged,
            ),
          ],
        ),
      ),
    );
  }
}
