Legacy Print Preview UI Compile Fix

This update fixes C# compile errors in DocArchivePrintPreview caused by assigning values to readonly fields outside the constructor.

Fixed error:
CS0191: A readonly field cannot be assigned to

Main file:
tools/DocArchivePrintPreview/Program.cs

After applying:
1. Run check script.
2. Rebuild DocArchivePrintPreview.
3. Install protocol.
4. Test docarchive-print:// protocol.
