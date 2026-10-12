import com.android.ide.common.pagealign.PageAlignUtilsKt;
import java.io.ByteArrayInputStream;
import java.util.zip.ZipFile;

/** Read-only native ELF verification using the installed Android Studio checker.
 * Does not check AAB ZIP alignment: APK ZIP alignment must be checked separately.
 * Run in Java source-file mode with Android Studio lib/* and plugins/android/lib/*
 * on the classpath, followed by this file and the AAB path.
 */
class VerifyAabElf {
    public static void main(String[] args) throws Exception {
        int libraries = 0;
        int skipped = 0;
        int problems = 0;
        try (var archive = new ZipFile(args[0])) {
            var entries = archive.entries();
            while (entries.hasMoreElements()) {
                var entry = entries.nextElement();
                if (!entry.getName().startsWith("base/lib/") || !entry.getName().endsWith(".so")) continue;
                try (var input = archive.getInputStream(entry)) {
                    var bytes = input.readAllBytes();
                    var elf = new ByteArrayInputStream(bytes);
                    // The SDK reader expects the four-byte magic to have been consumed.
                    if (!PageAlignUtilsKt.hasElfMagicNumber(elf)) throw new IllegalStateException("Not ELF: " + entry.getName());
                    var issues = PageAlignUtilsKt.readElfAlignmentProblems(elf);
                    if (issues == null) {
                        if (bytes[4] == 2) throw new IllegalStateException("Unable to parse 64-bit ELF: " + entry.getName());
                        skipped++;
                        continue;
                    }
                    libraries++;
                    if (!issues.isEmpty()) {
                        problems += issues.size();
                        System.out.println(entry.getName() + ": " + issues);
                    }
                }
            }
        }
        System.out.println("Android Studio ELF checker: libraries=" + libraries + ", skipped=" + skipped + ", problems=" + problems);
        if (problems != 0 || libraries == 0) System.exit(1);
    }
}
