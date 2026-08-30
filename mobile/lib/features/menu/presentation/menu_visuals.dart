import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';

IconData nusaMenuGroupIcon(String icon) {
  return switch (icon) {
    'school' => Icons.account_balance_rounded,
    'academic' => Icons.menu_book_rounded,
    'quiz' => Icons.quiz_rounded,
    'attendance' => Icons.fact_check_rounded,
    'counseling' => Icons.groups_rounded,
    'inventory' => Icons.inventory_2_rounded,
    'security' => Icons.security_rounded,
    _ => Icons.apps_rounded,
  };
}

Color nusaMenuGroupColor(String code) {
  return switch (code) {
    'kehadiran' => NusaColors.success,
    'ujian-asesmen' => const Color(0xFFEFAF08),
    'kesiswaan-bk' => const Color(0xFF7A56B3),
    'sarana-prasarana' => const Color(0xFF2B8793),
    'sistem' => const Color(0xFF536A86),
    _ => const Color(0xFF2676C8),
  };
}

IconData nusaMenuEntryIcon(MenuEntry item) {
  return switch (item.code) {
    'tahun-pelajaran' => Icons.calendar_month_rounded,
    'pegawai' => Icons.badge_rounded,
    'siswa' => Icons.school_rounded,
    'kelas' => Icons.class_rounded,
    'penempatan-siswa' => Icons.group_add_rounded,
    'kenaikan-kelas' => Icons.trending_up_rounded,
    'foto-identitas' => Icons.add_a_photo_rounded,
    'kartu-pegawai' => Icons.contact_page_rounded,
    'kartu-pelajar' => Icons.credit_card_rounded,
    'mata-pelajaran' => Icons.menu_book_rounded,
    'guru-mata-pelajaran' => Icons.co_present_rounded,
    'jadwal-mengajar-saya' => Icons.event_note_rounded,
    'jam-pelajaran' => Icons.schedule_rounded,
    'jadwal-pelajaran' => Icons.calendar_view_week_rounded,
    'skema-bobot-nilai' => Icons.balance_rounded,
    'komponen-nilai' => Icons.fact_check_rounded,
    'input-nilai' => Icons.edit_note_rounded,
    'rekap-nilai-rapor' => Icons.assessment_rounded,
    'nilai-saya' => Icons.workspace_premium_rounded,
    'pernyataan-survei' => Icons.ballot_rounded,
    'hasil-survei-saya' => Icons.poll_rounded,
    'monitoring-survei' => Icons.analytics_rounded,
    'perangkat-ajar-saya' => Icons.cloud_upload_rounded,
    'pemeriksaan-perangkat-ajar' => Icons.fact_check_rounded,
    'jenis-perangkat-ajar' => Icons.folder_copy_rounded,
    'pusat-cbt' => Icons.quiz_rounded,
    'jadwal-guru-piket' => Icons.shield_rounded,
    'piket-saya' => Icons.how_to_reg_rounded,
    'kegiatan-ibadah' => Icons.self_improvement_rounded,
    'jadwal-ibadah' => Icons.event_available_rounded,
    'pengaturan-berhalangan' => Icons.tune_rounded,
    'scan-berhalangan-ibadah' => Icons.lock_person_rounded,
    'konfirmasi-berhalangan-ibadah' => Icons.privacy_tip_rounded,
    'scan-ibadah-siswa' ||
    'scan-presensi-pegawai' => Icons.qr_code_scanner_rounded,
    'scan-presensi-siswa' => Icons.sensors_rounded,
    'rekap-ibadah-siswa' => Icons.bar_chart_rounded,
    'ringkasan-ibadah-bulanan' => Icons.calendar_view_month_rounded,
    'pengaturan-presensi-siswa' => Icons.settings_accessibility_rounded,
    'rekap-presensi-siswa' => Icons.fact_check_rounded,
    'laporan-presensi-siswa' => Icons.description_rounded,
    'pengaturan-presensi-pegawai' => Icons.manage_accounts_rounded,
    'rekap-presensi-pegawai' => Icons.assignment_turned_in_rounded,
    'laporan-presensi-pegawai' => Icons.summarize_rounded,
    'pemeriksaan-pengesahan' => Icons.verified_rounded,
    'daftar-laporan-siswa' => Icons.assignment_rounded,
    'pendampingan-siswa' => Icons.support_agent_rounded,
    'pelaksanaan-sanksi-siswa' => Icons.gavel_rounded,
    'peringatan-dini-siswa' => Icons.warning_amber_rounded,
    'rekap-poin-siswa' => Icons.score_rounded,
    'penghargaan-pengurangan-poin' => Icons.emoji_events_rounded,
    'penugasan-guru-wali' => Icons.supervisor_account_rounded,
    'laporkan-kejadian' => Icons.campaign_rounded,
    'jenis-pelanggaran-poin' => Icons.rule_rounded,
    'aturan-sanksi-poin' => Icons.policy_rounded,
    'poin-keterlambatan' => Icons.timer_rounded,
    'peringatan-dini-poin' => Icons.notification_important_rounded,
    'batas-proses-pelanggaran' => Icons.hourglass_bottom_rounded,
    'kategori-pembinaan-non-poin' => Icons.category_rounded,
    'katalog-barang' => Icons.storefront_rounded,
    'pengajuan-saya' => Icons.request_page_rounded,
    'dashboard-sarpras' => Icons.dashboard_rounded,
    'inventaris-barang' => Icons.inventory_2_rounded,
    'unit-aset' => Icons.qr_code_2_rounded,
    'label-inventaris' => Icons.label_rounded,
    'barang-datang' => Icons.move_to_inbox_rounded,
    'saldo-stok' => Icons.warehouse_rounded,
    'mutasi-stok' => Icons.swap_horiz_rounded,
    'peminjaman-barang' => Icons.outbox_rounded,
    'pengajuan-barang' => Icons.post_add_rounded,
    'pengembalian-barang' => Icons.assignment_return_rounded,
    'rekap-peminjaman' => Icons.receipt_long_rounded,
    'laporan-inventaris' => Icons.analytics_rounded,
    'kategori-barang' => Icons.category_rounded,
    'satuan-barang' => Icons.straighten_rounded,
    'lokasi-barang' => Icons.location_on_rounded,
    'sumber-perolehan' => Icons.source_rounded,
    'pengaturan-inventaris' => Icons.settings_rounded,
    'akun-pegawai' => Icons.manage_accounts_rounded,
    'akun-siswa' => Icons.account_circle_rounded,
    'akun-orang-tua' => Icons.family_restroom_rounded,
    'role-hak-akses' => Icons.admin_panel_settings_rounded,
    'aktivitas-login' => Icons.history_rounded,
    'backup-restore' => Icons.settings_backup_restore_rounded,
    _ => _fallbackMenuIcon(item),
  };
}

IconData _fallbackMenuIcon(MenuEntry item) {
  final value = '${item.code} ${item.label}'.toLowerCase();

  if (value.contains('jadwal')) return Icons.calendar_month_rounded;
  if (value.contains('laporan') || value.contains('rekap')) {
    return Icons.assessment_rounded;
  }
  if (value.contains('pengaturan')) return Icons.settings_rounded;
  if (value.contains('siswa')) return Icons.school_rounded;
  if (value.contains('pegawai') || value.contains('guru')) {
    return Icons.badge_rounded;
  }
  if (value.contains('barang') || value.contains('aset')) {
    return Icons.inventory_2_rounded;
  }

  return Icons.apps_rounded;
}
