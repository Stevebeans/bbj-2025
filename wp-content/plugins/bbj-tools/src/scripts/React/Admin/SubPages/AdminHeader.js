import React from "react";

function AdminHeader({ text }) {
  return (
    <div className="admin-header">
      <div className="text-lg">{text}</div>
    </div>
  );
}

export default AdminHeader;
