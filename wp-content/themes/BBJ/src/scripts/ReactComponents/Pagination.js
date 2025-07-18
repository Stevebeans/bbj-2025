import React from "react";

const Pagination = ({ totalPages, currentPage, onPageChange }) => {
  const pages = Array.from({ length: totalPages }, (_, i) => i + 1);

  return (
    <div className="pagination">
      {pages.map(page => (
        <button key={page} onClick={() => onPageChange(page)} className={`ml-0 rounded border border-gray-300 px-3 py-2 leading-tight text-gray-500 page-btn${currentPage === page ? "active bg-slate-200" : ""}`}>
          {page}
        </button>
      ))}
    </div>
  );
};

export default Pagination;
