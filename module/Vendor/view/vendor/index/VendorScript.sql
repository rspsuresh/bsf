

USE BSFV
GO

/****** Object:  Table [dbo].[Vendor_Machinery]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_Machinery](
	[MachineryTransId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_Machinery_VendorId]  DEFAULT ((0)),
	[Resource_ID] [int] NOT NULL CONSTRAINT [DF_Vendor_Machinery_Resource_ID]  DEFAULT ((0)),
	[Qty] [float] NOT NULL CONSTRAINT [DF_Vendor_Machinery_Qty]  DEFAULT ((0)),
	[Capacity] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Machinery_Capacity]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_Machinery] PRIMARY KEY CLUSTERED 
(
	[MachineryTransId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_ManPower]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Vendor_ManPower](
	[ManPowerTransId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_ManPower_VendorId]  DEFAULT ((0)),
	[Resource_ID] [int] NOT NULL CONSTRAINT [DF_Vendor_ManPower_Resource_ID]  DEFAULT ((0)),
	[Qty] [float] NOT NULL CONSTRAINT [DF_Vendor_ManPower_Qty]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_ManPower] PRIMARY KEY CLUSTERED 
(
	[ManPowerTransId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
/****** Object:  Table [dbo].[Vendor_ServiceMaster]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_ServiceMaster](
	[ServiceId] [int] IDENTITY(1,1) NOT NULL,
	[ServiceCode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_ServiceMaster_ServiceCode]  DEFAULT (''),
	[ServiceName] [varchar](8000) NULL CONSTRAINT [DF_Vendor_ServiceMaster_ServiceName]  DEFAULT (''),
	[ServiceGroupId] [int] NOT NULL CONSTRAINT [DF_Vendor_ServiceMaster_ServiceGroupId]  DEFAULT ((0)),
	[UnitId] [int] NOT NULL CONSTRAINT [DF_Vendor_ServiceMaster_UnitId]  DEFAULT ((0)),
	[ServiceDescription] [varchar](8000) NOT NULL CONSTRAINT [DF_Vendor_ServiceDescription_VNo]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_ServiceMaster] PRIMARY KEY CLUSTERED 
(
	[ServiceId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_TechPersons]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_TechPersons](
	[TechPersonId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_TechPersons_VendorId]  DEFAULT ((0)),
	[PersonName] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_TechPersons_PersonName]  DEFAULT (''),
	[Qualification] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_TechPersons_Qualification]  DEFAULT (''),
	[Experience] [float] NOT NULL CONSTRAINT [DF_Vendor_TechPersons_Experience]  DEFAULT ((0)),
	[Designation] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_TechPersons_Designation]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_TechPersons] PRIMARY KEY CLUSTERED 
(
	[TechPersonId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_CheckListMaster]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_CheckListMaster](
	[CheckListId] [int] IDENTITY(1,1) NOT NULL,
	[Description] [varchar](200) NOT NULL CONSTRAINT [DF_Vendor_CheckListMaster_Description]  DEFAULT (''),
	[Supply] [bit] NOT NULL CONSTRAINT [DF_Vendor_CheckListMaster_Supply]  DEFAULT ((0)),
	[Contract] [bit] NOT NULL CONSTRAINT [DF_Vendor_CheckListMaster_Contract]  DEFAULT ((0)),
	[Service] [bit] NOT NULL CONSTRAINT [DF_Vendor_CheckListMaster_Service]  DEFAULT ((0)),
	[MaxPoint] [float] NOT NULL CONSTRAINT [DF_Vendor_CheckListMaster_MaxPoint]  DEFAULT ((0)),
	[Approve] [bit] NOT NULL CONSTRAINT [DF_Vendor_CheckListMaster_Approve]  DEFAULT ((0)),
	[AssessmentType] [char](1) NOT NULL CONSTRAINT [DF_Vendor_CheckListMaster_AssessmentType]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_CheckListMaster] PRIMARY KEY CLUSTERED 
(
	[CheckListId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_GradeMaster]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_GradeMaster](
	[GradeID] [int] IDENTITY(1,1) NOT NULL,
	[GradeName] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_GradeMaster_GradeName]  DEFAULT (''),
	[FValue] [float] NOT NULL CONSTRAINT [DF_Vendor_GradeMaster_FromValue]  DEFAULT ((0)),
	[TValue] [float] NOT NULL CONSTRAINT [DF_Vendor_GradeMaster_ToValue]  DEFAULT ((0)),
	[Approve] [bit] NOT NULL CONSTRAINT [DF_Vendor_GradeMaster_Approve]  DEFAULT ((0))
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_Logistics]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_Logistics](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_Logistics_VendorId]  DEFAULT ((0)),
	[TransportArrange] [char](1) NOT NULL CONSTRAINT [DF_Vendor_Logistics_TransportArrange]  DEFAULT (''),
	[TransportMode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Logistics_TransportMode]  DEFAULT (''),
	[Delivery] [char](1) NOT NULL CONSTRAINT [DF_Vendor_Logistics_Delivery]  DEFAULT (''),
	[Insurance] [char](1) NOT NULL CONSTRAINT [DF_Vendor_Logistics_Insurance]  DEFAULT (''),
	[Unload] [char](1) NOT NULL CONSTRAINT [DF_Vendor_Logistics_Unload]  DEFAULT (''),
	[LogisticId] [int] IDENTITY(1,1) NOT NULL,
 CONSTRAINT [PK_Vendor_Logistics] PRIMARY KEY CLUSTERED 
(
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_ActivityTrans]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Vendor_ActivityTrans](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_ActivityTrans_VendorId]  DEFAULT ((0)),
	[ResourceGroupId] [int] NOT NULL CONSTRAINT [DF_Vendor_ActivityTrans_ResourceGroupId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_ActivityTrans] PRIMARY KEY CLUSTERED 
(
	[ResourceGroupId] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
/****** Object:  Table [dbo].[Vendor_Branch]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_Branch](
	[BranchId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_Branch_VendorId]  DEFAULT ((0)),
	[BranchName] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Branch_BranchName]  DEFAULT (''),
	[CityId] [int] NOT NULL CONSTRAINT [DF_Vendor_Branch_CityId]  DEFAULT ((0)),
	[Address] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Branch_Address]  DEFAULT (''),
	[TINNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Branch_TINNo]  DEFAULT (''),
	[Phone] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Branch_Phone]  DEFAULT (''),
	[ChequeNo] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Branch_ChequeNo]  DEFAULT (''),
	[CSTNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Branch_CSTNo]  DEFAULT (''),
	[DeleteFlag] [bit] NOT NULL CONSTRAINT [DF_Vendor_Branch_DeleteFlag]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_Branch] PRIMARY KEY CLUSTERED 
(
	[BranchId] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_BranchContactDetail]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_BranchContactDetail](
	[BranchTransId] [int] IDENTITY(1,1) NOT NULL,
	[BranchId] [int] NOT NULL CONSTRAINT [DF_Vendor_BranchContactDetail_BranchId]  DEFAULT ((0)),
	[ContactPerson] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_BranchContactDetail_ContactPerson]  DEFAULT (''),
	[Designation] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_BranchContactDetail_Designation]  DEFAULT (''),
	[ContactNo] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_BranchContactDetail_ContactNo]  DEFAULT (''),
	[Email] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_BranchContactDetail_Email]  DEFAULT (''),
	[Fax] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_BranchContactDetail_Fax]  DEFAULT (''),
	[DeleteFlag] [bit] NOT NULL CONSTRAINT [DF_Vendor_BranchContactDetail_DeleteFlag]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_BranchContactDetail] PRIMARY KEY CLUSTERED 
(
	[BranchTransId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_CheckListTrans]    Script Date: 27/05/2015 12:40:28 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_CheckListTrans](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_CheckListTrans_VendorId]  DEFAULT ((0)),
	[CheckListId] [int] NOT NULL CONSTRAINT [DF_Vendor_CheckListTrans_CheckListId]  DEFAULT ((0)),
	[RegType] [char](1) NOT NULL CONSTRAINT [DF_Vendor_CheckListTrans_RegType]  DEFAULT (''),
	[Points] [float] NOT NULL CONSTRAINT [DF_Vendor_CheckListTrans_Points]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_CheckListTrans] PRIMARY KEY CLUSTERED 
(
	[CheckListId] ASC,
	[RegType] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_Contact]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_Contact](
	[TransId] [int] IDENTITY(1,1) NOT NULL,
	[VendorID] [int] NOT NULL CONSTRAINT [DF_Vendor_Contact_VendorID]  DEFAULT ((0)),
	[CAddress] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Contact_CAddress]  DEFAULT (''),
	[Phone1] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Phone1]  DEFAULT (''),
	[Phone2] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Phone2]  DEFAULT (''),
	[Fax1] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Fax1]  DEFAULT (''),
	[Fax2] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Fax2]  DEFAULT (''),
	[Mobile1] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Mobile1]  DEFAULT (''),
	[Mobile2] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Mobile2]  DEFAULT (''),
	[CPerson1] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_CPerson1]  DEFAULT (''),
	[CPerson2] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_CPerson2]  DEFAULT (''),
	[CDesignation1] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_CDesignation1]  DEFAULT (''),
	[CDesignation2] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_CDesignation2]  DEFAULT (''),
	[ContactNo1] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_ContactNo1]  DEFAULT (''),
	[ContactNo2] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_ContactNo2]  DEFAULT (''),
	[Email1] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Email1]  DEFAULT (''),
	[Email2] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_Email2]  DEFAULT (''),
	[WebName] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Contact_WebName]  DEFAULT (''),
	[WebUpdate] [bit] NOT NULL CONSTRAINT [DF_Vendor_Contact_WebUpdate]  DEFAULT ((0)),
	[ContactType] [int] NOT NULL CONSTRAINT [DF_Vendor_Contact_ContactType]  DEFAULT ((0))
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_Experience]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_Experience](
	[ExperienceId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_Experience_VendorId]  DEFAULT ((0)),
	[WorkDescription] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Experience_WorkDescription]  DEFAULT (''),
	[ClientName] [varchar](200) NOT NULL CONSTRAINT [DF_Vendor_Experience_ClientName]  DEFAULT (''),
	[Value] [float] NOT NULL CONSTRAINT [DF_Vendor_Experience_Value]  DEFAULT ((0)),
	[Period] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Experience_Period]  DEFAULT (''),
	[Type] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Experience_Type]  DEFAULT (''),
	[WebUpdate] [bit] NOT NULL CONSTRAINT [DF_Vendor_Experience_WebUpdate]  DEFAULT ((0)),
	[DeleteFlag] [bit] NOT NULL CONSTRAINT [DF_Vendor_Experience_DeleteFlag]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_Experience] PRIMARY KEY CLUSTERED 
(
	[ExperienceId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_HireMachineryTrans]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Vendor_HireMachineryTrans](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_HireMachineryTrans_VendorId]  DEFAULT ((0)),
	[ResourceId] [int] NOT NULL CONSTRAINT [DF_Vendor_HireMachineryTrans_ResourceId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_HireMachineryTrans] PRIMARY KEY CLUSTERED 
(
	[ResourceId] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
/****** Object:  Table [dbo].[Vendor_Master]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_Master](
	[VendorId] [int] IDENTITY(1,1) NOT NULL,
	[VendorName] [varchar](200) NOT NULL CONSTRAINT [DF_Vendor_Master_VendorName]  DEFAULT (''),
	[UserName] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Master_UserName]  DEFAULT (''),
	[Password] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Master_Password]  DEFAULT (''),
	[Supply] [bit] NOT NULL CONSTRAINT [DF_Vendor_Master_Supplier]  DEFAULT ((0)),
	[Contract] [bit] NOT NULL CONSTRAINT [DF_Vendor_Master_Contractor]  DEFAULT ((0)),
	[Service] [bit] NOT NULL CONSTRAINT [DF_Vendor_Master_ServiceProvider]  DEFAULT ((0)),
	[RegAddress] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Master_RegAddress]  DEFAULT (''),
	[CityId] [int] NOT NULL CONSTRAINT [DF_Vendor_Master_CityId]  DEFAULT ((0)),
	[PinCode] [varchar](10) NOT NULL CONSTRAINT [DF_Vendor_Master_PinCode]  DEFAULT (''),
	[SupplyType] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Master_SupplyType]  DEFAULT (''),
	[ChequeNo] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Master_ChequeNo]  DEFAULT (''),
	[Code] [nvarchar](12) NOT NULL CONSTRAINT [DF_Vendor_Master_Code]  DEFAULT (''),
	[WebRegistration] [bit] NOT NULL CONSTRAINT [DF_Vendor_Master_WebLoginUpdate]  DEFAULT ((0)),
	[Live] [bit] NOT NULL CONSTRAINT [DF_Vendor_Master_Live]  DEFAULT ((0)),
	[Approve] [char](1) NOT NULL CONSTRAINT [DF_Vendor_Master_Approve_1]  DEFAULT ('N'),
	[DeleteFlag] [bit] NOT NULL CONSTRAINT [DF_Vendor_Master_DeleteFlag]  DEFAULT ((0)),
	[Company] [bit] NOT NULL CONSTRAINT [DF_Vendor_Master_Company]  DEFAULT ((0)),
	[CreatedDate] [datetime] NOT NULL CONSTRAINT [DF_Vendor_Master_CreatedDate]  DEFAULT (getdate()),
	[LastmodifiedDate] [datetime] NOT NULL CONSTRAINT [DF_Vendor_Master_LastmodifiedDate]  DEFAULT (getdate()),
 CONSTRAINT [PK_Vendor_Master] PRIMARY KEY CLUSTERED 
(
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_MaterialTrans]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_MaterialTrans](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_VendorId]  DEFAULT ((0)),
	[Resource_Id] [int] NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_Resource_Id]  DEFAULT ((0)),
	[Priority] [char](1) NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_Priority]  DEFAULT (''),
	[SupplyType] [char](1) NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_SupplyType]  DEFAULT (''),
	[LeadTime] [int] NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_LeadTime]  DEFAULT ((0)),
	[CreditDays] [int] NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_CreditDays]  DEFAULT ((0)),
	[ContactPerson] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_ContactPerson]  DEFAULT (''),
	[ContactNo] [varchar](13) NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_ContactNo]  DEFAULT (''),
	[Email] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_Email]  DEFAULT (''),
	[PotentialQty] [decimal](18, 3) NOT NULL CONSTRAINT [DF_Vendor_MaterialTrans_PotentialQty]  DEFAULT ((0)),
	[MaterialTransId] [int] IDENTITY(1,1) NOT NULL,
 CONSTRAINT [PK_Vendor_MaterialTrans] PRIMARY KEY CLUSTERED 
(
	[Resource_Id] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_ServiceTrans]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Vendor_ServiceTrans](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_ServiceTrans_VendorId]  DEFAULT ((0)),
	[ServiceId] [int] NOT NULL CONSTRAINT [DF_Vendor_ServiceTrans_ServiceId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_ServiceTrans] PRIMARY KEY CLUSTERED 
(
	[ServiceId] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
/****** Object:  Table [dbo].[Vendor_Statutory]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_Statutory](
	[VendorID] [int] NOT NULL CONSTRAINT [DF_Vendor_Statutory_VendorID]  DEFAULT ((0)),
	[FirmType] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_FirmType]  DEFAULT (''),
	[EYear] [int] NOT NULL CONSTRAINT [DF_Vendor_Statutory_EYear]  DEFAULT ((0)),
	[PANNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_PanNo]  DEFAULT (''),
	[TANNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_TANNo]  DEFAULT (''),
	[CSTNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_CSTNo]  DEFAULT (''),
	[TINNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_TINNo]  DEFAULT (''),
	[ServiceTaxNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_ServiceTaxNo]  DEFAULT (''),
	[CSTDate] [datetime] NOT NULL CONSTRAINT [DF_Vendor_Statutory_CSTDate]  DEFAULT (getdate()),
	[TNGSTNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_TNGSTNo]  DEFAULT (''),
	[BankAccountNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_BankAccountNo]  DEFAULT (''),
	[AccountType] [char](1) NOT NULL CONSTRAINT [DF_Vendor_Statutory_AccountType]  DEFAULT (''),
	[BankName] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Statutory_BankName]  DEFAULT (''),
	[BranchName] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_BranchName]  DEFAULT (''),
	[BranchCode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_BranchCode]  DEFAULT (''),
	[MICRCode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_MICRCode]  DEFAULT (''),
	[IFSCCode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_IFSCCode]  DEFAULT (''),
	[SSIREGDNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_SSIREGDNo]  DEFAULT (''),
	[ServiceTaxCir] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_ServiceTaxCir]  DEFAULT (''),
	[EPFNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_EPFNo]  DEFAULT (''),
	[ESINo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_ESINo]  DEFAULT (''),
	[ExciseVendor] [bit] NOT NULL CONSTRAINT [DF_Vendor_Statutory_ExciseVendor]  DEFAULT ((0)),
	[ExciseRegNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_ExciseRegNo]  DEFAULT (''),
	[Excisedivision] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_Excisedivision]  DEFAULT (''),
	[ExciseRange] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_ExciseRange]  DEFAULT (''),
	[ECCno] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_Statutory_ECCno]  DEFAULT (''),
	[VatRemittance] [int] NOT NULL CONSTRAINT [DF_Vendor_Statutory_VatRemittance]  DEFAULT ((0)),
	[RemittanceDate] [datetime] NULL,
	[WebUpdate] [bit] NOT NULL CONSTRAINT [DF_Vendor_Statutory_WebUpdate]  DEFAULT ((0)),
	[DeleteFlag] [bit] NOT NULL CONSTRAINT [DF_Vendor_Statutory_DeleteFlag]  DEFAULT ((0)),
	[ChequeonName] [varchar](200) NOT NULL CONSTRAINT [DF_Vendor_Statutory_ChequeonName]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_Statutory] PRIMARY KEY CLUSTERED 
(
	[VendorID] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[VendorStatutoryBankDetail]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_StatutoryBankDetail](
	[StatutoryTransId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_VendorId]  DEFAULT ((0)),
	[BankAccountNo] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_BankAccountNo]  DEFAULT (''),
	[AccountType] [char](1) NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_AccountType]  DEFAULT (''),
	[BankName] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_BankName]  DEFAULT (''),
	[BranchName] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_BranchName]  DEFAULT (''),
	[BranchCode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_BranchCode]  DEFAULT (''),
	[MICRCode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_MICRCode]  DEFAULT (''),
	[IFSCCode] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_IFSCCode]  DEFAULT (''),
	[DefaultBank] [bit] NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_DefaultBank]  DEFAULT ((0)),
	[DeleteFlag] [bit] NOT NULL CONSTRAINT [DF_Vendor_StatutoryBankDetail_DeleteFlag]  DEFAULT ((0))
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_TurnOver]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
SET ANSI_PADDING ON
GO
CREATE TABLE [dbo].[Vendor_TurnOver](
	[TurnOverId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_TurnOver_VendorId]  DEFAULT ((0)),
	[FYear] [varchar](50) NOT NULL CONSTRAINT [DF_Vendor_TurnOver_FYear]  DEFAULT (''),
	[Value] [float] NOT NULL CONSTRAINT [DF_Vendor_TurnOver_Value]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_TurnOver] PRIMARY KEY CLUSTERED 
(
	[TurnOverId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_WorkGroup]    Script Date: 27/05/2015 12:40:29 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
CREATE TABLE [dbo].[Vendor_WorkGroup](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_WorkGroup_VendorId]  DEFAULT ((0)),
	[WorkGroupId] [int] NOT NULL CONSTRAINT [DF_Vendor_WorkGroup_WorkGroupId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_WorkGroup] PRIMARY KEY CLUSTERED 
(
	[VendorId] ASC,
	[WorkGroupId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
/****** Object:  Table [dbo].[Vendor_Location]    Script Date: 27/05/2015 3:48:41 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [dbo].[Vendor_Location](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_Location_VendorId]  DEFAULT ((0)),
	[CityId] [int] NOT NULL CONSTRAINT [DF_Vendor_Location_CityId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_Location] PRIMARY KEY CLUSTERED 
(
	[CityId] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO

/****** Object:  Table [dbo].[Vendor_CertificateTrans]    Script Date: 27/05/2015 3:50:13 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [dbo].[Vendor_CertificateTrans](
	[VendorId] [int] NOT NULL  CONSTRAINT [DF_Vendor_CertificateTrans_VendorId]  DEFAULT ((0)),
	[CertificateId] [int] NOT NULL CONSTRAINT [DF_Vendor_CertificateTrans_CertificateId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_CertificateTrans] PRIMARY KEY CLUSTERED 
(
	[CertificateId] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
/****** Object:  Table [dbo].[Vendor_CertificateMaster]    Script Date: 27/05/2015 3:53:05 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

SET ANSI_PADDING ON
GO

CREATE TABLE [dbo].[Vendor_CertificateMaster](
	[CertificateId] [int] IDENTITY(1,1) NOT NULL,
	[CerDescription] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_CertificateMaster_CerDescription]  DEFAULT (''),
	[Date] [datetime] NOT NULL CONSTRAINT [DF_Vendor_CertificateMaster_Date]  DEFAULT (getdate()),
	[Type] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_CertificateMaster_Type]  DEFAULT (''),
	[UpLoad] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_CertificateMaster_UpLoad]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_CertificateMaster] PRIMARY KEY CLUSTERED 
(
	[CertificateId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO

SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_Enclosure]    Script Date: 27/05/2015 3:59:43 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

SET ANSI_PADDING ON
GO

CREATE TABLE [dbo].[Vendor_Enclosure](
	[EnclosureId] [int] IDENTITY(1,1) NOT NULL,
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_Enclosure_VendorId]  DEFAULT ((0)),
	[Location] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Enclosure_Location]  DEFAULT (''),
	[Date] [datetime] NOT NULL CONSTRAINT [DF_Vendor_Enclosure_Date]  DEFAULT (getdate()),
	[Name] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Enclosure_Name]  DEFAULT (''),
	[Type] [varchar](100) NOT NULL CONSTRAINT [DF_Vendor_Enclosure_Type]  DEFAULT (''),
	[Remarks] [varchar](255) NOT NULL CONSTRAINT [DF_Vendor_Enclosure_Remarks]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_Enclosure] PRIMARY KEY CLUSTERED 
(
	[EnclosureId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO

SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_TransportMaster]    Script Date: 27/05/2015 4:01:29 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

SET ANSI_PADDING ON
GO

CREATE TABLE [dbo].[Vendor_TransportMaster](
	[TransportId] [int] IDENTITY(1,1) NOT NULL,
	[TransportName] [varchar](200) NOT NULL CONSTRAINT [DF_Vendor_TransportMaster_TransportName]  DEFAULT (''),
 CONSTRAINT [PK_Vendor_TransportMaster] PRIMARY KEY CLUSTERED 
(
	[TransportId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO

SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_Transport]    Script Date: 27/05/2015 4:03:16 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE TABLE [dbo].[Vendor_Transport](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_VendorTransport_VendorId]  DEFAULT ((0)) ,
	[TransportId] [int] NOT NULL CONSTRAINT [DF_VendorTransport_TransportId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_Transport] PRIMARY KEY CLUSTERED 
(
	[TransportId] ASC,
	[VendorId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO
/****** Object:  Table [dbo].[Vendor_ServiceGroup]    Script Date: 27/05/2015 5:10:54 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

SET ANSI_PADDING ON
GO

CREATE TABLE [dbo].[Vendor_ServiceGroup](
	[ServiceGroupId] [int] IDENTITY(1,1) NOT NULL,
	[ServiceGroupName] [varchar](500) NOT NULL CONSTRAINT [DF_Vendor_ServiceGroup_ServiceGroupName]  DEFAULT (''),
	[ServiceTypeId] [int] NOT NULL CONSTRAINT [DF_Vendor_ServiceGroup_ServiceTypeId]  DEFAULT ((0)),
	[TDSTypeId] [int] NOT NULL CONSTRAINT [DF_Vendor_ServiceGroup_TDSTypeId]  DEFAULT ((0)),
 CONSTRAINT [PK_Vendor_ServiceGroup] PRIMARY KEY CLUSTERED 
(
	[ServiceGroupId] ASC
)WITH (PAD_INDEX = OFF, STATISTICS_NORECOMPUTE = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS = ON, ALLOW_PAGE_LOCKS = ON) ON [PRIMARY]
) ON [PRIMARY]

GO

SET ANSI_PADDING OFF
GO
/****** Object:  Table [dbo].[Vendor_SupplierDet]    Script Date: 28/05/2015 4:23:42 PM ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

SET ANSI_PADDING ON
GO

CREATE TABLE [dbo].[Vendor_SupplierDet](
	[VendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_SupplierDet_VendorId]  DEFAULT ((0)),
	[SupplierType] [char](1) NOT NULL CONSTRAINT [DF_Vendor_SupplierDet_SupplierType]  DEFAULT (''),
	[SupplierVendorId] [int] NOT NULL CONSTRAINT [DF_Vendor_SupplierDet_SupplierVendorId]  DEFAULT ((0))
) ON [PRIMARY]

GO

SET ANSI_PADDING OFF
GO