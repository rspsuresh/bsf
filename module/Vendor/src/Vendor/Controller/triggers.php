CREATE TRIGGER LastModifyDate
ON dbo.Vendor_Master
AFTER UPDATE
AS
    UPDATE dbo.Vendor_Master
    SET LastModifiedDate = GETDATE()
    WHERE VendorId IN (SELECT DISTINCT VendorId FROM Inserted)