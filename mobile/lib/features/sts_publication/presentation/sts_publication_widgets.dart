import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/sts_publication/data/sts_publication_repository.dart';

class StsPublicationHeader extends StatelessWidget {
  const StsPublicationHeader({
    required this.title,
    required this.subtitle,
    this.metrics = const {},
    this.icon = Icons.description_rounded,
    super.key,
  });
  final String title, subtitle;
  final Map<String, String> metrics;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(18),
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: NusaColors.accent, size: 24),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Text(
          subtitle,
          style: const TextStyle(
            color: Color(0xFFCBDDF0),
            fontSize: 12,
            height: 1.5,
          ),
        ),
        if (metrics.isNotEmpty) ...[
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (_, constraints) => Wrap(
              runSpacing: 12,
              spacing: 12,
              children: [
                for (final entry in metrics.entries)
                  SizedBox(
                    width: MediaQuery.textScalerOf(context).scale(12) > 20
                        ? constraints.maxWidth
                        : (constraints.maxWidth - 12 * (metrics.length - 1)) /
                              metrics.length,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          entry.value,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                            fontSize: 20,
                          ),
                        ),
                        Text(
                          entry.key,
                          style: const TextStyle(
                            color: Color(0xFFCBDDF0),
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ],
    ),
  );
}

class StsPublicationNotice extends StatelessWidget {
  const StsPublicationNotice(this.text, {this.warning = false, super.key});
  final String text;
  final bool warning;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: warning ? const Color(0xFFFFF8E6) : NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(
          warning ? Icons.info_outline_rounded : Icons.shield_outlined,
          size: 20,
          color: NusaColors.primary,
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(text, style: const TextStyle(fontSize: 12, height: 1.5)),
        ),
      ],
    ),
  );
}

class StsPublicationSurface extends StatelessWidget {
  const StsPublicationSurface({required this.child, super.key});
  final Widget child;
  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),
      side: const BorderSide(color: NusaColors.outline),
    ),
    clipBehavior: Clip.antiAlias,
    child: Padding(padding: const EdgeInsets.all(14), child: child),
  );
}

class StsPublicationBadge extends StatelessWidget {
  const StsPublicationBadge(this.text, {this.success = false, super.key});
  final String text;
  final bool success;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: success ? NusaColors.successSurface : NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      text,
      style: TextStyle(
        fontSize: 11,
        fontWeight: FontWeight.w600,
        color: success ? NusaColors.success : NusaColors.primary,
      ),
    ),
  );
}

class StsPublicationFacts extends StatelessWidget {
  const StsPublicationFacts(this.values, {super.key});
  final Map<String, Object> values;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (_, constraints) => Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final entry in values.entries)
          Container(
            width: MediaQuery.textScalerOf(context).scale(13) > 22
                ? constraints.maxWidth
                : (constraints.maxWidth - 10) / 2,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: NusaColors.surfaceBlue,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  entry.key,
                  style: const TextStyle(
                    fontSize: 11,
                    color: NusaColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${entry.value}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class PublishedStsReportCard extends StatelessWidget {
  const PublishedStsReportCard({
    required this.report,
    required this.onTap,
    super.key,
  });
  final StsData report;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.description_rounded,
                    color: NusaColors.primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        stsText(stsMap(report['siswa'])['nama']),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        '${stsText(stsMap(report['kelas'])['nama'])} · ${stsText(stsMap(report['kegiatan'])['tahun_pelajaran'])}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: NusaColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, size: 20),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              stsText(stsMap(report['kegiatan'])['nama']),
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                const StsPublicationBadge('Terpublikasi', success: true),
                if (report['sertakan_perilaku'] == true)
                  const StsPublicationBadge('Lampiran perilaku'),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              'Versi ${report['versi']} · Terbit ${stsDate(report['dipublikasikan_pada'])}',
              style: const TextStyle(
                fontSize: 11,
                color: NusaColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
