import React from 'react';
import { createRoot } from 'react-dom/client';
import {
    RiAddLine,
    RiArrowDownLine,
    RiArrowDownSLine,
    RiArrowLeftRightLine,
    RiArrowUpLine,
    RiArrowUpDownLine,
    RiArrowUpSLine,
    RiAttachment2,
    RiBankLine,
    RiBookOpenLine,
    RiBuilding2Line,
    RiCalculatorLine,
    RiCalendarLine,
    RiCheckboxLine,
    RiCloudLine,
    RiDashboardLine,
    RiDeleteBin6Line,
    RiDownloadCloud2Line,
    RiErrorWarningLine,
    RiEyeLine,
    RiEyeOffLine,
    RiFileExcel2Line,
    RiFilePdf2Line,
    RiFilter3Line,
    RiGitBranchLine,
    RiInformationLine,
    RiLayoutColumnLine,
    RiLineChartLine,
    RiListCheck3,
    RiListUnordered,
    RiLockLine,
    RiLogoutBoxRLine,
    RiMenuLine,
    RiMoonLine,
    RiPrinterLine,
    RiReceiptLine,
    RiSave3Line,
    RiSearchLine,
    RiShieldCheckLine,
    RiShieldKeyholeLine,
    RiSparkling2Line,
    RiSunLine,
    RiTimeLine,
    RiUserSettingsLine,
    RiWallet3Line,
} from 'react-icons/ri';

const icons = {
    accountList: RiListCheck3,
    add: RiAddLine,
    arrowDown: RiArrowDownLine,
    arrowDownUp: RiArrowUpDownLine,
    arrowLeftRight: RiArrowLeftRightLine,
    arrowUp: RiArrowUpLine,
    attachment: RiAttachment2,
    bank: RiBankLine,
    book: RiBookOpenLine,
    branch: RiGitBranchLine,
    building: RiBuilding2Line,
    calculator: RiCalculatorLine,
    calendar: RiCalendarLine,
    cashStack: RiWallet3Line,
    check: RiCheckboxLine,
    cloudDownload: RiDownloadCloud2Line,
    columns: RiLayoutColumnLine,
    dashboard: RiDashboardLine,
    danger: RiErrorWarningLine,
    eye: RiEyeLine,
    eyeOff: RiEyeOffLine,
    fileExcel: RiFileExcel2Line,
    filePdf: RiFilePdf2Line,
    filter: RiFilter3Line,
    graphDown: RiArrowDownSLine,
    graphUp: RiLineChartLine,
    info: RiInformationLine,
    journal: RiBookOpenLine,
    journalCheck: RiCheckboxLine,
    journalPlus: RiBookOpenLine,
    list: RiMenuLine,
    lock: RiLockLine,
    lockFill: RiLockLine,
    logout: RiLogoutBoxRLine,
    moon: RiMoonLine,
    pending: RiTimeLine,
    printer: RiPrinterLine,
    receipt: RiReceiptLine,
    role: RiShieldKeyholeLine,
    save: RiSave3Line,
    search: RiSearchLine,
    shield: RiShieldCheckLine,
    sortAsc: RiArrowUpSLine,
    sortDesc: RiArrowDownSLine,
    success: RiCheckboxLine,
    sun: RiSunLine,
    trash: RiDeleteBin6Line,
    user: RiUserSettingsLine,
    wallet: RiWallet3Line,
};

export function Icon({ name, className = '', title = null }) {
    const Component = icons[name] || RiSparkling2Line;

    return (
        <Component
            aria-hidden={title ? undefined : true}
            aria-label={title || undefined}
            className={className}
            focusable="false"
        />
    );
}

function renderIcons(root = document) {
    root.querySelectorAll('[data-icon]:not([data-icon-mounted])').forEach((element) => {
        element.setAttribute('data-icon-mounted', 'true');
        const iconRoot = element.__iconRoot || createRoot(element);
        element.__iconRoot = iconRoot;
        iconRoot.render(<Icon name={element.dataset.icon} className="app-icon-svg" title={element.getAttribute('data-icon-title')} />);
    });
}

window.renderIcons = renderIcons;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => renderIcons());
} else {
    renderIcons();
}
